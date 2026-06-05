const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  entry: {
    schedule: './src/schedule/index.js',
  },
};
