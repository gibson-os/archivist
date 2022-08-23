Ext.define('GibsonOS.module.archivist.model.Strategy', {
    extend: 'GibsonOS.data.Model',
    idProperty: 'className',
    fields: [{
        name: 'className',
        type: 'string'
    },{
        name: 'name',
        type: 'string'
    },{
        name: 'configurationStep',
        type: 'int'
    },{
        name: 'parameters',
        type: 'object'
    }]
});