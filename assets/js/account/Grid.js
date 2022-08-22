Ext.define('GibsonOS.module.archivist.account.Grid', {
    extend: 'GibsonOS.module.core.component.grid.Panel',
    alias: ['widget.gosModuleArchivistAccountGrid'],
    requiredPermission: {
        module: 'archivist',
        task: 'account'
    },
    initComponent() {
        let me = this;

        me.store = new GibsonOS.module.archivist.store.Account();

        me.callParent();
    },
    addFunction() {
        // new GibsonOS.module.archivist.account.Window();
    },
    deleteFunction(records) {
        const me = this;

        GibsonOS.MessageBox.show({
            type: GibsonOS.MessageBox.type.INFO,
            title: 'Wirklich löschen?',
            msg: 'Möchtest du die ' + records.length + ' Account' + (records.length === 0 ? '' : 's') + ' wirklich löschen?',
            buttons: [{
                text: 'Ja',
                handler() {
                    me.setLoading(true);

                    accounts = [];

                    Ext.iterate(records, record => {
                        accounts.push({id: record.get('id')});
                    });

                    GibsonOS.Ajax.request({
                        url: baseDir + 'archivist/account/delete',
                        params: {
                            accounts: Ext.encode(accounts)
                        },
                        success() {
                            me.getStore().load();
                        },
                        callback() {
                            me.setLoading(false);
                        }
                    });
                }
            },{
                text: 'Nein'
            }]
        });
    },
    enterFunction(record) {
        // const window = new GibsonOS.module.archivist.rule.Window({ruleId: record.get('id')});
        // const formPanel = window.down('gosModuleArchivistRuleForm');
        // const form = formPanel.getForm();
        // const configuration = record.get('configuration');
        //
        // const beforeAddFieldsFunctions = (parameters) => {
        //     formPanel.un('beforeAddFields', beforeAddFieldsFunctions);
        //
        //     Ext.iterate(parameters, (name, parameter) => {
        //         console.log('set value ' + configuration[name] + ' for ' + name);
        //         parameter.value = configuration[name] ?? null;
        //     });
        // };
        // formPanel.on('beforeAddFields', beforeAddFieldsFunctions);
        // form.findField('strategy').setValue(record.get('strategy'));
        // form.setValues(record.get('configuration'));
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
            dataIndex: 'active',
            text: 'Aktiv',
            width: 50
        }];
    }
});