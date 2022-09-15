Ext.define('GibsonOS.module.archivist.rule.TabPanel', {
    extend: 'GibsonOS.TabPanel',
    alias: ['widget.gosModuleArchivistRuleTabPanel'],
    itemId: 'archivistIndexPanel',
    layout: 'fit',
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistRuleGrid',
            title: 'Regeln',
            accountId: me.accountId
        },{
            xtype: 'gosModuleArchivistIndexGrid',
            title: 'Indexierte Dateien',
            accountId: me.accountId
        }];

        me.callParent();
    }
});