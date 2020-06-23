angular.module('codeBaseAdminApp')
    .controller('dataCapture', function ($scope, $templateCache, $compile, $http, $location, $mdDialog, Dialog, $rootScope, Toast) {

        $scope.$parent.validation = {
            exlude_decline_reasons: {
                status: true,
                message: ''
            }
        };

        $scope.loadComplete = true;
        $scope.allowed_decline_msg_separator = '\n';
        $scope.data_destinations = ['local', 'external'];
       // $scope.types = ["prospect", "orders", "prospect+orders"];
        $scope.types = [
            {
                label: "Prospect",
                value: 'prospect'
            },
            {
                label: "Orders",
                value: 'checkout_upsell_downsell'
            },
            {
                label: "Prospect+Orders",
                value: 'prospect_checkout_upsell_downsell'
            }
        ]


        if (!$scope.extension.hasOwnProperty('data_destination') || !$scope.extension.data_destination.length > 0) {
            $scope.extension.data_destination = ["external"];
        }
        if (!$scope.extension.hasOwnProperty('data_capture_types') || !$scope.extension.data_capture_types.length > 0) {
            $scope.extension.data_capture_types = "checkout_upsell_downsell";
        }
        
        if(angular.isUndefined($scope.extension.capture_sesitive_data)) {
            $scope.extension.capture_sesitive_data = true;
        }
    });