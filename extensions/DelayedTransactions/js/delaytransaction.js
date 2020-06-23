angular.module('codeBaseAdminApp')
    .controller('delayTransaction', function ($scope, Toast) {
        //$scope.delay_type_list = ['Fixed','Dynamic'];   
        $scope.extension.authAmount = [];
        $scope.defaultKeys = {
            'cardTypeAuth': function () {
                return   {
                    'card_type': '',
                    'auth_amount': ''
                }
            }
        };
        $scope.cards = ['visa', 'amex', 'master', 'discover', 'diners', 'jcb'];
        
        $scope.addAuthAmountField = function (val) {
            $scope.extension.authAmount.push(parseInt(val));
        };
        
        $scope.add = function (multiKeyIndex) {
            $scope.extension[multiKeyIndex].push($scope.defaultKeys[multiKeyIndex]());
        }
        
        $scope.remove = function (index, multiKeyIndex) {
            $scope.extension[multiKeyIndex].splice(index, 1);
        }
        
        for (var key in $scope.defaultKeys) {
            if (!$scope.extension.hasOwnProperty(key) || !$scope.extension[key].length > 0) {
                $scope.extension[key] = [];
                $scope.extension[key].push($scope.defaultKeys[key]());
            }
        }
        
        $scope.addStepMethod = function () {
        var lastItem = $scope.extension.step_campaign_map[$scope.extension.step_campaign_map.length - 1];
            if((angular.isUndefined(lastItem.authorization_amount) || lastItem.authorization_amount == '') || (angular.isUndefined(lastItem.campaign_id) || lastItem.campaign_id == '')) {
                Toast.showToast('Step ID or Campaign ID can not be blank.');
                return;
            }
            $scope.extension.step_campaign_map.push($scope.defaultStepCampaignWiseMethods());
        };
        $scope.removeStep = function (index) {
            $scope.extension.step_campaign_map.splice(index, 1);
        }

        $scope.defaultStepCampaignWiseMethods = function () {
            return {
                'authorization_amount' : '', 
                'campaign_id' : '', 
            }
        };

        if (typeof $scope.extension.step_campaign_map == "undefined" || 
            !$scope.extension.step_campaign_map.length > 0) {
            $scope.extension.step_campaign_map = [];
            $scope.extension.step_campaign_map.push($scope.defaultStepCampaignWiseMethods());
        }
});