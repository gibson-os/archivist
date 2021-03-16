Ext.define('GibsonOS.module.archivist.rule.Grid', {
    extend: 'GibsonOS.module.core.component.grid.Panel',
    alias: ['widget.gosModuleArchivistRuleGrid'],
    requiredPermission: {
        module: 'archivist',
        task: 'rule'
    },
    initComponent() {
        let me = this;

        me.store = new GibsonOS.module.archivist.rule.store.Grid();

        me.callParent();
    },
    addFunction: function() {
        new GibsonOS.module.archivist.rule.Window();
    },
    enterFunction: function(record) {
        const window =new GibsonOS.module.archivist.rule.Window();
        window.down('gosModuleArchivistRuleForm').loadRecord(record);
    },
    getColumns() {
        return [{
            dataIndex: 'name',
            text: 'Name',
            flex: 1
        }, {
            dataIndex: 'observedDirectory',
            text: 'Beobachtetes Verzeichnis',
            flex: 1
        }, {
            dataIndex: 'observedFilename',
            text: 'Beobachtete Dateinamen',
            flex: 1
        }, {
            dataIndex: 'moveDirectory',
            text: 'Ziel Verzeichnis',
            flex: 1
        }, {
            dataIndex: 'moveFilename',
            text: 'Ziel Dateiname',
            flex: 1
        }, {
            dataIndex: 'count',
            text: 'Anzahl',
            width: 50
        }, {
            dataIndex: 'active',
            text: 'Aktiv',
            width: 50
        }];
    }
});