Ext.define('GibsonOS.module.archivist.index.TabPanel', {
    extend: 'GibsonOS.TabPanel',
    alias: ['widget.gosModuleArchivistIndexTabPanel'],
    itemId: 'archivistIndexPanel',
    layout: 'fit',
    initComponent: function () {
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