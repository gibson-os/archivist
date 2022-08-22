Ext.define('GibsonOS.module.archivist.rule.App', {
    extend: 'GibsonOS.App',
    alias: ['widget.gosModuleArchivistRuleApp'],
    title: 'Archivator Regeln',
    appIcon: 'icon_scan',
    width: 700,
    height: 500,
    requiredPermission: {
        module: 'archivist',
        task: 'index'
    },
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistRuleGrid'
        }];

        me.callParent();
    }
});