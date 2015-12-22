app.factory('Database', ['$http', function ($http) {
    'use strict';

    function getEntity(params) {
      if (params.type === undefined) params.type = 'update';
      return $http.get(baseUrl + '/admin/getEntity', {params: params});
    }

    function saveEntity(entity, coll) {
      var ajaxOpts = {
        id: entity._id,
        coll: coll,
        type: 'update',
        duplicate_rates: false,
        data: JSON.stringify(entity)
      };
      return $http.post(baseUrl + '/admin/save', ajaxOpts);
    }

    function getAvailablePlans() {
      return $http.get(baseUrl + '/admin/getAvailablePlans');
    }

    function getCollectionItems(params) {
      var params = {
        coll: params.collection
      };
      return $http.get(baseUrl + '/admin/getCollectionItems', {params: params});
    }

    return {
      getEntity: getEntity,
      saveEntity: saveEntity,
      getAvailablePlans: getAvailablePlans,
      getCollectionItems: getCollectionItems
    };
  }]);