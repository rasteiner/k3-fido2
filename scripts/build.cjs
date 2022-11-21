const dev = process.argv.includes('--dev');

const path = require('path');
const esbuild = require('esbuild');
const vuePlugin = require('esbuild-vue');

esbuild.build({
  entryPoints: [
    path.resolve(__dirname, '../src/index.ts'),
  ],
  outfile: path.resolve(__dirname, '../index.js'),
  minify: !dev,
  bundle: true,
  splitting: false,
  plugins: [vuePlugin({
    extractCss: true,
  })],
  format: 'iife',
  target: 'esnext',
  define: {
    "process.env.NODE_ENV": JSON.stringify(dev ? "development" : "production"),
  },
  loader: {
    '.svg': 'text',
  },
  watch: dev && {
    onRebuild: (err) => {
      if (err) {
        console.error(err);
      }
    }
  }
})