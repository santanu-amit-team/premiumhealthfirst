angular.module('codeBaseAdminApp')
.controller('LenderLBP', function ($scope, $templateCache, $compile, $http, $location, $mdDialog, Dialog, $rootScope, Toast) {

    $scope.LBPCategory = [ 'ProtectShip', 'eMagazine' ];
    $scope.gatewayType = ['default','filebased'];

    $scope.addMethod = function () {
        var lastItem = $scope.extension.trail_compeltion[$scope.extension.trail_compeltion.length - 1];
        if((angular.isUndefined(lastItem.step) || lastItem.label == '') || (angular.isUndefined(lastItem.step) || lastItem.label == '')) {
            Toast.showToast('Trial Completion Step or Label can not be blank.');
            return;
        }
        $scope.extension.trail_compeltion.push($scope.defaultStepWiseMethods());
    };
    $scope.remove = function (index) {
        $scope.extension.trail_compeltion.splice(index, 1);
    }

    $scope.defaultStepWiseMethods = function () {
        return {
            'step' : '', 
            'label' : 'Trial', 
        }
    };

    if (typeof $scope.extension.trail_compeltion == "undefined" || 
        !$scope.extension.trail_compeltion.length > 0) {
        $scope.extension.trail_compeltion = [];
        $scope.extension.trail_compeltion.push($scope.defaultStepWiseMethods());
    }

    $scope.$on('extensionEdited', function(event, data) {
        if (!data.success) {
            // If extension save failed
            $scope.LenderLBPForm.$submitted = false;
        }
        else {
            // if extension save successfully done
        }
    });

}).filter('ucfirst', function() {
    return function(input) {
      return (angular.isString(input) && input.length > 0) ? input.charAt(0).toUpperCase() + input.substr(1).toLowerCase() : input;
    }
});