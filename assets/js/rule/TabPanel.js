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
            xtype: 'gosModuleArchivistIndexGrid',
            title: 'Indexierte Dateien',
            //ruleId: me.ruleId
        }];

        me.callParent();
    }
});