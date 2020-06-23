angular.module('codeBaseAdminApp')
    .controller('limlightUtil', function ($scope, $templateCache, $compile, Toast, $http, $location, $mdDialog, Dialog, $rootScope, Toast) {
        $scope.limelightUtilForm = {};
        $scope.limelightUtilForm.$submitted = true;
        $scope.trackingTypeList = ['default'];
        $scope.add_custom_field = '';
        $scope.$parent.validation = {
            custom_scripts: {
                status: true,
                message: ''
            }
        };
        
        if (!$scope.extension.hasOwnProperty('custom_fileds') || !$scope.extension.custom_fileds.length > 0) {
            $scope.extension.custom_fileds = [];
        }
        
        var defaultOptions = {
            prospect_note : true,
            order_note : true,
            ga_code : 'UA-80325941-3'
        };
        
        angular.forEach(defaultOptions, function(v, k) {
            if(typeof $scope.extension[k] === 'undefined')
            {
                $scope.$parent.extension[k] = v;
            }
        });
        
        
        $scope.extension.custom_fileds = $scope.$parent.extension.custom_fileds;
        $scope.defaultCustomFileds = function () {
            return {
                'custom_filed': ''
            }
        };
        $scope.addMethod = function (val) {
            if (!val) {
                Toast.showToast('Add Custom Field');
                return false;
            }
            for (var value of $scope.extension.custom_fileds) {
                if (val === value) {
                     $scope.extension.custom_fileds[index] = "";
                    Toast.showToast('Already Exists');
                    return false;
                }
            }

            $scope.extension.custom_fileds.push(val);
            $scope.add_custom_field = '';
        };
        $scope.remove = function (index) {
            $scope.extension.custom_fileds.splice(index, 1);
        }

        $scope.edit = function (index) {
            if (!$scope.extension.custom_fileds[index]) {
                Toast.showToast('Add Custom Field');
                return false;
            }
            var editVal = $scope.extension.custom_fileds[index];
            var source = $scope.extension.custom_fileds;
            for (var key in source) {
                if (editVal === source[key] && key != index) {
                    $scope.extension.custom_fileds[index] = "";
                    Toast.showToast('Already Exists');
                    return false;
                }
            }

        }

        $scope.checkDuplicate = function (source, value, skipKey) {
            if (typeof source !== "undefined" && source.length > 0) {
                for (var val of source) {
                    if (val === value) {
                        return false;
                    }
                }
//                if (!skipKey) {
//                    console.log("AS");
//                    for (var val of source) {
//                        if(val === value){
//                            return false;
//                        }
//                    }
//                } else {
//                    var i = 0;
//                    for (var val of source) {
//                        if(skipKey !== i && val === value){
//                            return false;
//                        }
//                        i++;
//
//                    }
//                }
            }
            return true;
        }
    });