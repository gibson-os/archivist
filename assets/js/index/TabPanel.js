Ext.define('GibsonOS.module.archivist.index.TabPanel', {
    extend: 'GibsonOS.TabPanel',
    alias: ['widget.gosModuleArchivistIndexTabPanel'],
    itemId: 'archivistIndexPanel',
    layout: 'fit',
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistAccountGrid',
            title: 'Regeln'
        },{
            title: 'Indexierte Dateien'
        }];

        me.callParent();
    }
});