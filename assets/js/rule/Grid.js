Ext.define('GibsonOS.module.archivist.rule.Grid', {
    extend: 'GibsonOS.grid.Panel',
    alias: ['widget.gosModuleArchivistRuleGrid'],
    itemId: 'archivistRuleGrid',
    requiredPermission: {
        module: 'archivist',
        task: 'rule'
    },
    initComponent: function () {
        let me = this;

        me.store = new GibsonOS.module.archivist.rule.store.Grid();
        me.columns = [{
            dataIndex: 'name',
            text: 'Name',
            flex: 1
        },{
            dataIndex: 'observeDirectory',
            text: 'Beobachtes Verzeichnis',
            flex: 1
        },{
            dataIndex: 'observeFilename',
            text: 'Beobachtete Dateinamen',
            flex: 1
        },{
            dataIndex: 'moveDirectory',
            text: 'Ziel Verzeichnis',
            flex: 1
        },{
            dataIndex: 'moveFilename',
            text: 'Ziel Dateiname',
            flex: 1
        },{
            dataIndex: 'count',
            text: 'Anzahl',
            width: 50
        },{
            dataIndex: 'active',
            text: 'Aktiv',
            width: 50
        }];
        me.dockedItems = [{
            xtype: 'gosToolbar',
            dock: 'top',
            items: []
        },{
            xtype: 'gosToolbarPaging',
            itemId: 'explorerHtml5Paging',
            store: me.store,
            displayMsg: 'Regeln {0} - {1} von {2}',
            emptyMsg: 'Keine Regeln vorhanden'
        }];

        me.callParent();

        //me.on('itemdblclick', GibsonOS.module.explorer.html5.listener.itemDblClick);
    }
});