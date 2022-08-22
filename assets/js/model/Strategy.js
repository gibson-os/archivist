Ext.define('GibsonOS.module.archivist.model.Strategy', {
    extend: 'GibsonOS.data.Model',
    idProperty: 'strategy',
    fields: [{
        name: 'strategy',
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