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
        const form = window.down('gosModuleArchivistRuleForm').getForm();

        form.findField('strategy').setValue(record.get('strategy'));
        console.log(form.getFields());
        console.log(form.getFields().findBy(function(f) {
            console.log(f);
            return f.id === 'directory' || f.getName() === 'directory';
        }));
        console.log(form.findField('directory'));
        form.setValues(record.get('configuration'));
    },
    getColumns() {
        return [{
            dataIndex: 'name',
            text: 'Name',
            flex: 1
        },{
            dataIndex: 'strategy',
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