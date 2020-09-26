Ext.define('GibsonOS.module.archivist.rule.model.Grid', {
    extend: 'GibsonOS.data.Model',
    fields: [{
        name: 'id',
        type: 'int'
    },{
        name: 'name',
        type: 'string'
    },{
        name: 'observedDirectory',
        type: 'string'
    },{
        name: 'observedFilename',
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