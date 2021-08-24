Ext.define('GibsonOS.module.archivist.rule.model.Grid', {
    extend: 'GibsonOS.data.Model',
    fields: [{
        name: 'id',
        type: 'int'
    },{
        name: 'name',
        type: 'string'
    },{
        name: 'strategy',
        type: 'string'
    },{
        name: 'strategyName',
        type: 'string'
    },{
        name: 'configuration',
        type: 'object'
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
        name: 'lastRun',
        type: 'date'
    }]
});