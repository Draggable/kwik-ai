const path = require('path');

module.exports = {
  // Only the Gutenberg "description" block needs bundling — it imports
  // node modules (rc-slider) and uses wp.element/JSX. The other admin scripts
  // (admin.js, settings.js, featured-image.js) are hand-written jQuery loaded
  // directly from assets/js and are intentionally NOT part of the build.
  entry: {
    'description-block': './assets/src/description-block.js',
  },
  output: {
    // Emit the bundle straight to where the plugin enqueues it.
    path: path.resolve(__dirname, 'assets/js'),
    filename: '[name].js',
    // IMPORTANT: do NOT enable `clean` here. assets/js also contains the
    // hand-written jQuery files, and cleaning would delete them.
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react'],
          },
        },
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader'],
      },
    ],
  },
  resolve: {
    extensions: ['.js', '.jsx'],
  },
  optimization: {
    minimize: true,
  },
};
