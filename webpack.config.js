const Encore = require('@symfony/webpack-encore');

Encore
.setOutputPath('public/')
.setPublicPath('/bundles/markocupicresourcebooking')
.setManifestKeyPrefix('')

//.addEntry('backend', './assets/backend.js')
//.addEntry('frontend', './assets/frontend.js')

.copyFiles({
    from: './assets/audio',
    to: 'audio/[path][name].[hash:8].[ext]',
})
.copyFiles({
    from: './assets/icons',
    to: 'icons/[path][name].[ext]',
})
.copyFiles({
    from: './assets',
    to: 'js/[path][name].[hash:8].[ext]',
    pattern: /(app\.js)$/,
})
.copyFiles({
    from: './assets/styles',
    to: 'css/[path][name].[hash:8].[ext]',
    pattern: /(frontend\.css)$/,
})
.copyFiles({
    from: './node_modules/vue/dist',
    to: 'vue/[path][name].[hash:8].[ext]',
    pattern: /(vue\.global\.prod\.js)$/,
})

.disableSingleRuntimeChunk()
.cleanupOutputBeforeBuild()
.enableSourceMaps()
.enableVersioning()

// enables @babel/preset-env polyfills
.configureBabelPresetEnv((config) => {
    config.useBuiltIns = 'usage';
    config.corejs = 3;
})

.enablePostCssLoader()
;

module.exports = Encore.getWebpackConfig();
