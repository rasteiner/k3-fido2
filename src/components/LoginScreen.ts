import LoginWithPasskey from './LoginWithPasskey.vue';
import { originalLogin } from '../index';

export default {
  props: {
    methods: Array
  },
  data() {
    return {
      showPassword: false,
    }
  },
  render(h) {
    // inject our component into the default login screen
    // this is a render function because `originalLogin` isn't available at startup
    return h('div', [
      this.showPassword
        ? h('div', [
            h(originalLogin, { props: { methods: this.methods } }),
            h('hr'),
            h('k-button', {
              props: {
                icon: 'touch',
              },
              on: {
                click: () => this.showPassword = false
              }
            }, [
              'Use Passkey'
            ])
          ])
        : h(LoginWithPasskey, { on: { cancel: () => this.showPassword = true } })
    ]);
  }
}