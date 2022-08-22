Ext.define('GibsonOS.module.archivist.rule.TabPanel', {
    extend: 'GibsonOS.TabPanel',
    alias: ['widget.gosModuleArchivistRuleTabPanel'],
    itemId: 'archivistIndexPanel',
    layout: 'fit',
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistRuleGrid',
            title: 'Regeln'
        },{
            title: 'Indexierte Dateien'
        }];

        me.callParent();
    }
});