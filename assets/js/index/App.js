Ext.define('GibsonOS.module.archivist.index.App', {
    extend: 'GibsonOS.App',
    alias: ['widget.gosModuleArchivistIndexApp'],
    title: 'Archivator',
    appIcon: 'icon_scan',
    width: 700,
    height: 500,
    requiredPermission: {
        module: 'archivist',
        task: 'index'
    },
    initComponent: function() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistIndexTabPanel'
        }];

        me.callParent();
    }
});