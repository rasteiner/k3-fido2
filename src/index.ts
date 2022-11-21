import AccountView from './components/AccountView.vue';
import LoginScreen from './components/LoginScreen';
import TouchIcon from './icons/touch.svg';

export const enableResidentKeys = true;
export let originalLogin = null;


panel.plugin('rasteiner/k3-passkeys', {
  use: [
    (Vue) => {
      originalLogin = Vue.component('k-login');
    }
  ],
  sections: {
    passkey: AccountView,
  },
  icons: {
    'touch': TouchIcon,
  },
  login: LoginScreen
});
