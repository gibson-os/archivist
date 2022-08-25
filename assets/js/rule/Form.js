Ext.define('GibsonOS.module.archivist.rule.Form', {
    extend: 'GibsonOS.module.core.component.form.Panel',
    alias: ['widget.gosModuleArchivistRuleForm'],
    ruleId: null,
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosCoreComponentFormFieldTextField',
            name: 'name',
            fieldLabel: 'Name'
        },{
            xtype: 'gosCoreComponentFormFieldTextField',
            name: 'observedFilename',
            fieldLabel: 'Beobachtete Dateinamen'
        },{
            xtype: 'gosCoreComponentFormFieldTextField',
            name: 'observedContent',
            fieldLabel: 'Beobachteter Inhalt'
        }, {
            xtype: 'gosCoreComponentFormFieldContainer',
            fieldLabel: 'Ziel Verzeichnis',
            items: [{
                xtype: 'gosCoreComponentFormFieldTextField',
                name: 'moveDirectory',
                margins: '0 5 0 0'
            }, {
                xtype: 'gosButton',
                flex: 0,
                text: '...',
                handler() {
                    GibsonOS.module.explorer.dir.fn.dialog(me.getForm().findField('moveDirectory'));
                }
            }]
        },{
            xtype: 'gosCoreComponentFormFieldTextField',
            name: 'moveFilename',
            fieldLabel: 'Ziel Dateiname'
        },{
            xtype: 'gosCoreComponentFormFieldCheckbox',
            name: 'active',
            fieldLabel: 'Aktiv'
        }];

        me.buttons = [{
            text: 'Speichern',
            handler() {
                me.setLoading(true);

                me.getForm().submit({
                    xtype: 'gosFormActionAction',
                    url: baseDir + 'archivist/rule/save',
                    params: {
                        id: me.ruleId,
                        accountId: me.accountId
                    },
                    failure() {
                        me.setLoading(false);
                    },
                    success() {
                        me.setLoading(false);
                    }
                });
            }
        }];

        me.callParent();
    }
});