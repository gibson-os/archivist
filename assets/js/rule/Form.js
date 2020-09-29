Ext.define('GibsonOS.module.archivist.rule.Form', {
    extend: 'GibsonOS.form.Panel',
    alias: ['widget.gosModuleArchivistRuleForm'],
    itemId: 'archivistRuleForm',
    defaults: {
        border: false,
        xtype: 'panel',
        flex: 1,
        layout: 'anchor'
    },
    border: false,
    initComponent: function () {
        let me = this;

        me.items = [{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Name',
            name: 'name'
        },{
            xtype: 'fieldcontainer',
            fieldLabel: 'Beobachtetes Verzeichnis',
            layout: 'hbox',
            defaults: {
                hideLabel: true
            },
            items: [{
                xtype: 'gosFormTextfield',
                name: 'observedDirectory',
                flex: 1,
                margins: '0 5 0 0'
            },{
                xtype: 'gosButton',
                text: '...',
                handler: function() {
                    GibsonOS.module.explorer.dir.fn.dialog(me.getForm().findField('observedDirectory'));
                }
            }]
        },{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Beobachtete Dateinamen',
            name: 'observedFilename'
        },{
            xtype: 'fieldcontainer',
            fieldLabel: 'Ziel Verzeichnis',
            layout: 'hbox',
            defaults: {
                hideLabel: true
            },
            items: [{
                xtype: 'gosFormTextfield',
                name: 'moveDirectory',
                flex: 1,
                margins: '0 5 0 0'
            },{
                xtype: 'gosButton',
                text: '...',
                handler: function() {
                    GibsonOS.module.explorer.dir.fn.dialog(me.getForm().findField('moveDirectory'));
                }
            }]
        },{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Ziel Dateiname',
            name: 'moveFilename'
        },{
            xtype: 'gosFormNumberfield',
            fieldLabel: 'Anzahl',
            name: 'count',
            value: 0
        },{
            xtype: 'gosFormCheckbox',
            name: 'active',
            fieldLabel: '&nbsp;',
            labelSeparator: '',
            boxLabel: 'Aktiv',
            uncheckedValue: false,
            inputValue: true
        }];

        me.buttons = [{
            text: 'Speichern',
            itemId: 'archivistRuleFormSaveButton',
            requiredPermission: {
                action:'save',
                permission: GibsonOS.Permission.WRITE
            },
            handler: function() {
                me.getForm().submit({
                    xtype: 'gosFormActionAction',
                    url: baseDir + 'archivist/rule/save',
                    success: function(form, action) {
                        me.fireEvent('afterSaveForm', {
                            form: me,
                            action: action
                        });
                    }
                });
            }
        }];

        me.callParent();
    }
});