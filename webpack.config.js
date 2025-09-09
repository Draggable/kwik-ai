const path = require('path');

module.exports = {
  entry: './assets/js/description-block.js',
  output: {
    filename: 'description-block.build.js',
    path: path.resolve(__dirname, 'assets/js')
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react']
          }
        }
      }
    ]
  }
};