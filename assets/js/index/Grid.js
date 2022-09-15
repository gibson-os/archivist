Ext.define('GibsonOS.module.archivist.index.Grid', {
    extend: 'GibsonOS.module.core.component.grid.Panel',
    alias: ['widget.gosModuleArchivistIndexGrid'],
    multiSelect: true,
    accountId: null,
    ruleId: null,
    requiredPermission: {
        module: 'archivist',
        task: 'index'
    },
    initComponent() {
        let me = this;

        me.store = new GibsonOS.module.archivist.store.Index({
            accountId: me.accountId,
            ruleId: me.ruleId
        });

        me.callParent();
    },
    getColumns() {
        return [{
            dataIndex: 'inputPath',
            text: 'Eingangspfad',
            flex: 1
        },{
            dataIndex: 'outputPath',
            text: 'Ausgangspfad',
            flex: 1
        },{
            dataIndex: 'size',
            text: 'Größe',
            align: 'right',
            width: 70,
            renderer(value) {
                return transformSize(value);
            }
        },{
            dataIndex: 'error',
            text: 'Fehler',
            flex: 1
        },{
            dataIndex: 'changed',
            text: 'Änderungsdatum',
            width: 120
        }];
    }
});