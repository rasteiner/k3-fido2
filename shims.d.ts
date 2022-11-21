declare module "*.vue" {
  import type { DefineComponent } from "vue";
  const component: DefineComponent<{}, {}, any>;
  export default component;
}

declare module "*.svg" {
  const content: string;
  export default content;
}

declare namespace panel {
  interface Plugin {
    use: any[];
    sections: any;
    icons: any;
    login: any;
  }
  function plugin(name: string, plugin: Plugin): void;
}