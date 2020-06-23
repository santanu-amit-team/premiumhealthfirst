/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

angular.module('codeBaseAdminApp')
.controller('CustomInputMask', function ($scope, $templateCache, $compile, Toast, $http, $location, $mdDialog, Dialog, $rootScope, Toast) {

    $scope.devices = ['desktop', 'mobile'];
    $scope.placeholder = [ 'blank', 'cross' ];
    $scope.MaskingTypes = [ 'no_masking', 'dash_masking', 'space_masking' ];
    
}).filter('underscoreless', function () {
    return function (input) {
        return input.replace(/_/g, ' ');
    };
});