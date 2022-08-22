Ext.define('GibsonOS.module.archivist.model.Rule', {
    extend: 'GibsonOS.data.Model',
    fields: [{
        name: 'id',
        type: 'int'
    },{
        name: 'name',
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
        name: 'lastRun',
        type: 'date'
    }]
});