const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/')
    .setPublicPath('/bundles/markocupicresourcebooking')
    .setManifestKeyPrefix('')

    // Week calendar of the front end (assets/rbb/*.js, without dependencies)
    .addEntry('js/app', './assets/app.js')

    .copyFiles({
        from: './assets/icons',
        to: 'icons/[path][name].[ext]',
    })
    .copyFiles({
        from: './assets',
        to: 'js/[path][name].[hash:8].[ext]',
        pattern: /backend\.js$/,
    })
    .copyFiles({
        from: './assets/styles',
        to: 'css/[path][name].[hash:8].[ext]',
        pattern: /(backend\.css)$/,
    })

    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps()
    .enableVersioning()

    // Preprocessing scss in css
    .enablePostCssLoader()
    .enableSassLoader()
    .enablePostCssLoader()
    .addStyleEntry('css/frontend', './assets/styles/frontend.scss')
    .addStyleEntry('css/backend', './assets/styles/backend.scss')

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })

    .enablePostCssLoader()
;

module.exports = Encore.getWebpackConfig();
