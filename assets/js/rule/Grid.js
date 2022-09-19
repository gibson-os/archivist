Ext.define('GibsonOS.module.archivist.rule.Grid', {
    extend: 'GibsonOS.module.core.component.grid.Panel',
    alias: ['widget.gosModuleArchivistRuleGrid'],
    multiSelect: true,
    requiredPermission: {
        module: 'archivist',
        task: 'rule'
    },
    initComponent() {
        let me = this;

        me.store = new GibsonOS.module.archivist.store.Rule({
            accountId: me.accountId
        });

        me.callParent();
    },
    addFunction() {
        const me = this;
        const window = new GibsonOS.module.archivist.rule.Window({
            accountId: me.accountId
        });

        window.down('form').getForm().on('actioncomplete', () => {
            me.getStore().load();
        })
    },
    enterFunction(record) {
        const me = this;
        const window = new GibsonOS.module.archivist.rule.Window({
            accountId: me.accountId,
            ruleId: record.get('id')
        });
        const form = window.down('form').getForm();

        form.loadRecord(record);
        form.on('actioncomplete', () => {
            me.getStore().load();
        });
    },
    deleteFunction(records) {
        const me = this;

        Ext.MessageBox.confirm(
            'Wirklich löschen?',
            'Möchtest du die ' + records.length + ' Regel' + (records.length === 0 ? '' : 'n') + ' wirklich löschen?', buttonId => {
                if (buttonId === 'no') {
                    return false;
                }

                me.setLoading(true);

                let rules = [];

                Ext.iterate(records, record => {
                    rules.push({id: record.get('id')});
                });

                GibsonOS.Ajax.request({
                    url: baseDir + 'archivist/rule/delete',
                    params: {
                        'rules': Ext.encode(rules)
                    },
                    success() {
                        me.getStore().load();
                    },
                    callback() {
                        me.setLoading(false);
                    }
                });
            }
        );
    },
    getColumns() {
        return [{
            dataIndex: 'name',
            text: 'Name',
            flex: 1
        },{
            dataIndex: 'observedFilename',
            text: 'Beobachtete Dateinamen',
            flex: 1
        },{
            dataIndex: 'observedContent',
            text: 'Beobachteter Inhalt',
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
            dataIndex: 'active',
            text: 'Aktiv',
            width: 50
        }];
    }
});