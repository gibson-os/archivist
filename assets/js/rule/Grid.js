Ext.define('GibsonOS.module.archivist.rule.Grid', {
    extend: 'GibsonOS.module.core.component.grid.Panel',
    alias: ['widget.gosModuleArchivistRuleGrid'],
    requiredPermission: {
        module: 'archivist',
        task: 'rule'
    },
    initComponent() {
        let me = this;

        me.store = new GibsonOS.module.archivist.rule.store.Rule();

        me.callParent();
    },
    addFunction() {
        new GibsonOS.module.archivist.rule.Window();
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

                ruleIds = [];

                Ext.iterate(records, record => {
                    ruleIds.push(record.get('id'));
                });

                GibsonOS.Ajax.request({
                    url: baseDir + 'archivist/rule/delete',
                    params: {
                        'ruleIds[]': ruleIds
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
    enterFunction(record) {
        const window = new GibsonOS.module.archivist.rule.Window({ruleId: record.get('id')});
        const formPanel = window.down('gosModuleArchivistRuleForm');
        const form = formPanel.getForm();
        const configuration = record.get('configuration');

        const beforeAddFieldsFunctions = (parameters) => {
            formPanel.un('beforeAddFields', beforeAddFieldsFunctions);

            Ext.iterate(parameters, (name, parameter) => {
                console.log('set value ' + configuration[name] + ' for ' + name);
                parameter.value = configuration[name] ?? null;
            });
        };
        formPanel.on('beforeAddFields', beforeAddFieldsFunctions);
        form.findField('strategy').setValue(record.get('strategy'));
        form.setValues(record.get('configuration'));
    },
    getColumns() {
        return [{
            dataIndex: 'name',
            text: 'Name',
            flex: 1
        },{
            dataIndex: 'strategyName',
            text: 'Strategy',
            flex: 1
        },{
            dataIndex: 'observedFilename',
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
            dataIndex: 'active',
            text: 'Aktiv',
            width: 50
        }];
    }
});