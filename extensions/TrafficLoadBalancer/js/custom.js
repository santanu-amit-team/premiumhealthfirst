angular.module('codeBaseAdminApp')
    .controller('traffircLoadbalancer', function ($scope, $templateCache, $compile, Toast, $http, $location, $mdDialog, Dialog, $rootScope, Toast) {
        $scope.steps = [1, 2, 3, 4, 5];
        $scope.engine = ['flat', 'random'];
        $scope.cards = ['visa', 'amex', 'master', 'discover', 'diners', 'jcb'];
        $scope.affiliatesList = ['affid', 'afid', 'sid', 'c1', 'c2', 'c3', 'c4', 'c5', 'aid', 'opt', 'click_id'];
        $scope.defaultKeys = {
            'scheduler': function () {
                return   {
                    'start_time': '',
                    'end_time': ''
                }
            },
            'productFilter': function () {
                return   {
                    'productID': '',
                    'percentage': ''
                }
            },
            'cardFilter': function () {
                return {
                    'card_type': '',
                    'card_percentage': '',
                    'card_filter_config': ''
                }
            },
            'affiliateFilter': function () {
                return {
                    'aff_id': '',
                    'step1': '',
                    'step2': '',
                    'step3': '',
                    'step4': '',
                    'step5': '',
                }
            },
            'affiliates': function () {
                return {
                    'aff_param': '',
                    'mapped_param': '',
                }
            }
        };
        $scope.scrapping_method_required = false;
        $scope.existing_dynamic_model = ['step1', 'step2', 'step3', 'step4', 'step5'];

        $scope.default_order_filter_percentage = [];
        for (var key of $scope.existing_dynamic_model) {
            if ($scope.extension.hasOwnProperty('default_settings') &&
                $scope.extension.default_settings.hasOwnProperty(key) && $scope.extension.default_settings[key] !== 0) {

                $scope.default_order_filter_percentage.push(parseInt(key.split('step')[1]));
            }
        }


        $scope.addStep = function (val) {
            if ($scope.default_order_filter_percentage.indexOf(parseInt(val)) !== -1 || typeof val === "undefined") {
                return;
            }
            $scope.default_order_filter_percentage.push(parseInt(val));
        };

        $scope.removeSteps = function (index, val) {
            $scope.default_order_filter_percentage.splice(index, 1);
            $scope.extension[$scope.existing_dynamic_model[(val - 1)]] = 0;
            delete $scope.extension.default_settings[$scope.existing_dynamic_model[(val - 1)]];
        }

        for (var key in $scope.defaultKeys) {
            if (!$scope.extension.hasOwnProperty(key) || !$scope.extension[key].length > 0) {
                $scope.extension[key] = [];
                $scope.extension[key].push($scope.defaultKeys[key]());
            }
        }


        $scope.add = function (multiKeyIndex) {

            $scope.extension[multiKeyIndex].push($scope.defaultKeys[multiKeyIndex]());

        }
        $scope.remove = function (index, multiKeyIndex) {

            $scope.extension[multiKeyIndex].splice(index, 1);

        };

        $http.post('../' + REST_API_PATH + 'affiliates/all/')
            .success(function (response) {
                $scope.affiliateList = response.data;
            });

        $scope.checkStepWisePercentage = function (index, step) {

            if (typeof $scope.extension.affiliateFilter[index]['step1'] === "undefined" ||
                $scope.extension.affiliateFilter[index]['step1'] == '' ||
                parseInt($scope.extension.affiliateFilter[index]['step1']) == 0) {
                Toast.showToast('Step1 Percentage required for affiliate based order filter');
                $scope.loadbalacerForm.$submitted = false;
                return;
            }


            if ($scope.extension.affiliateFilter[index][step] != '' &&
                parseInt($scope.extension.affiliateFilter[index][step]) <
                parseInt($scope.extension.affiliateFilter[index]['step1'])
                ) {
                Toast.showToast('Step1 percnetage should not greater than other steps!');
                $scope.loadbalacerForm.$submitted = false;
                return;
            }
            return;

        };

        $scope.scrappingMethodValidation = function () {
            if ($scope.extension.enable_affiliate_orderfilter ||
                $scope.extension.enable_card_scrapper ||
                $scope.extension.enable_product_orderfilter ||
                $scope.enable_schedule) {
                $scope.scrapping_method_required = true;
            } else {
                $scope.scrapping_method_required = false;
            }
        };
        $scope.scrappingMethodValidation();

        if (!$scope.extension.enable_default_settings) {
            if(typeof $scope.extension.default_settings === "undefined")
                $scope.extension.default_settings = {};
            $scope.extension.default_settings.enable_remote = false;
            $scope.extension.default_settings.disable_prepaid_orderfilter = false;
            $scope.extension.default_settings.disable_test_order = false;
        }
        $scope.setDefaultConfigStatus = function () {
            if (!$scope.extension.enable_default_settings) {
                $scope.extension.default_settings.enable_remote = false;
                $scope.extension.default_settings.disable_test_order = false;
                $scope.extension.default_settings.disable_prepaid_orderfilter = false;
            }
        }
        $scope.saveExtension = function(){
             $scope.$parent.saveExtension();
             $scope.loadbalacerForm.$submitted = false;
        }
    });