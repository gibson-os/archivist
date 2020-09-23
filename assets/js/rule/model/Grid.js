Ext.define('GibsonOS.module.archivist.rule.model.Grid', {
    extend: 'GibsonOS.data.Model',
    fields: [{
        name: 'id',
        type: 'int'
    },{
        name: 'name',
        type: 'string'
    },{
        name: 'observeDirectory',
        type: 'string'
    },{
        name: 'observeFilename',
        type: 'string'
    },{
        name: 'moveDirectory',
        type: 'string'
    },{
        name: 'moveFilename',
        type: 'string'
    },{
        name: 'active',
        type: 'bool'
    },{
        name: 'count',
        type: 'int'
    }]
});