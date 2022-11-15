let orig;
/**
 * Convert a ArrayBuffer to Base64
 * @param {ArrayBuffer} buffer
 * @returns {String}
 */
function arrayBufferToBase64(buffer) {
  let binary = '';
  let bytes = new Uint8Array(buffer);
  let len = bytes.byteLength;
  for (let i = 0; i < len; i++) {
    binary += String.fromCharCode( bytes[ i ] );
  }
  return window.btoa(binary);
}

/**
 * decode base64url to ArrayBuffer
 */
function b64ToArrayBuffer(source) {
  // Convert base64url to base64
  let encoded = source.replace(/-/g, '+').replace(/_/g, '/');

  // Add padding
  encoded += '='.repeat((4 - encoded.length % 4) % 4);

  return Uint8Array.from(atob(encoded), c => c.charCodeAt(0)).buffer;
}

const loginWithPasskeyComponent = {
  template: `
    <form class="k-login-with-passkey" @submit.prevent="submit">
      <k-fieldset :novalidate="true" :fields="fields" v-model="user"/>

      <div class="k-login-buttons">
        <k-button
          icon="cancel"
          @click="$emit('cancel');"
          type="button"
        >
          Login with password
        </k-button>

        <k-button
          icon="touch"
          class="k-login-button"
          type="submit"
        >{{$t('login')}}</k-button>
      </div>
    </form>
  `,
  data() {
    return {
      user: { email: '' }
    };
  },
  computed: {
    fields() {
      return {
        email: {
          type: 'email',
          required: true,
          label: this.$t('email'),
          icon: 'user',
          autocomplete: 'email',
        }
      }
    }
  },
  methods: {
    cancel() {
      this.$emit('cancel');
    },
    async submit() {
      const getArgs = await this.$api.get('passkeys/get/args')
      getArgs.publicKey.challenge = b64ToArrayBuffer(getArgs.publicKey.challenge);

      try {

        const cred = await navigator.credentials.get( getArgs );
        const response = {
          id: cred.rawId ? arrayBufferToBase64(cred.rawId) : null,
          clientDataJSON: cred.response.clientDataJSON ? arrayBufferToBase64(cred.response.clientDataJSON) : null,
          authenticatorData: cred.response.authenticatorData ? arrayBufferToBase64(cred.response.authenticatorData) : null,
          signature: cred.response.signature ? arrayBufferToBase64(cred.response.signature) : null,
          userHandle: cred.response.userHandle ? arrayBufferToBase64(cred.response.userHandle) : null
        };

        const login = await this.$api.post('passkeys/get/process', {
          email: this.user.email,
          response: response
        });

        if (login?.success) {
          this.$reload({
            globals: ["$system", "$translation"]
          });
        }

      } catch (e) {
        console.log(e);
        return;
      }
    }
  }
}

const AccountViewComponent = {
  data() {
    return {
      passkeys: [],
    }
  },
  props: {
    label: String,
  },
  template: `
    <section class="k-passkey-section" :label="label">
      <header class="k-section-header">
        <k-headline>{{label}}</k-headline>

        <k-button-group v-if="$view.id === 'account'" :buttons="[{
          text: 'Register new',
          click: 'registerNew',
          icon: 'touch'
        }]" />
      </header>
      <div>
        <div v-if="passkeys?.length">
          <k-items
            :items="passkeys"
            :columns="{
              rpId: { label: 'Relying Party', type: 'text' },
              credentialId: { label: 'Credential ID', type: 'text' },
            }"
            layout="table"
          />
        </div>
        <div v-else>
          <k-empty>
            No passkeys yet.
          </k-empty>
        </div>
      </div>
    </section>
  `,
  created() {
    this.load().then(response => {
      this.passkeys = response.passkeys;
    })
  },
  methods: {
    async registerNew() {
      const args = await this.$api.get('passkeys/create/args');

      // convert strings to ArrayBuffer
      args.publicKey.challenge = b64ToArrayBuffer(args.publicKey?.challenge);
      args.publicKey.allowCredentials = args.publicKey?.allowCredentials?.map((cred) => {
        cred.id = b64ToArrayBuffer(cred.id);
        return cred;
      });
      args.publicKey.excludeCredentials = args.publicKey?.excludeCredentials?.map((cred) => {
        cred.id = b64ToArrayBuffer(cred.id);
        return cred;
      });
      args.publicKey.user.id = b64ToArrayBuffer(args.publicKey?.user?.id);

      // request credentials
      const creds = await navigator.credentials.create(args);

      if (creds?.response?.clientDataJSON) {
        const clientData = arrayBufferToBase64(creds.response.clientDataJSON);
        const attestationObject = arrayBufferToBase64(creds.response.attestationObject);

        const response = await this.$api.post('passkeys/create/process', { clientData, attestationObject });
        this.passkeys = response.passkeys;
      }
    }
  }
};

panel.plugin('rasteiner/k3-passkeys', {
  use: [
    (Vue) => {
      orig = Vue.component('k-login');
      console.log(orig);
    }
  ],
  sections: {
    passkey: AccountViewComponent,
  },
  icons: {
    'touch': '<g stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" transform="translate(0 0.5)"><path d="M5.5,15.5V7A2.5,2.5,0,0,1,8,4.5H8A2.5,2.5,0,0,1,10.5,7v8.5"></path> <path d="M2.876,11a6.5,6.5,0,1,1,10.248,0"></path> <line x1="7.5" y1="8.5" x2="8.5" y2="8.5"></line></g>',
  },
  login: {
    props: {
      methods: Array
    },
    data() {
      return {
        showPassword: false,
      }
    },
    render(h) {
      return h('div', [
        this.showPassword
          ? h('div', [
            h(orig, { props: { methods: this.methods } }),
            h('hr'),
            h('button', {
              class: 'rs-passkey-button',
              on: {
                click: () => this.showPassword = false
              }
            }, [
              h('k-icon', { props: { type: 'touch' } }),
              'Login with Passkey'
            ])
          ])
          : h(loginWithPasskeyComponent, { on: { cancel: () => this.showPassword = true } })
      ]);
    }
  }
});
