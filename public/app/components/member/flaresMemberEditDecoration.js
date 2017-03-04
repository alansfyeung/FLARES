var flaresApp = angular.module('flaresMemberDecorationViewEdit', ['flaresBase']);

flaresApp.controller('memberEditDecorationController', function($scope, $rootScope, $controller, $http, $timeout, flAPI, flResource){
    
    // Extend this controller with resourceController
    angular.extend(this, $controller('resourceController', {$scope: $scope})); 
    
    var c = this;
    c.extendConfig({
        'unloadWarning': 'You are editing this decoration record, and will lose any unsaved changes.'
    });
    
    $scope.state = Object.create(c.state);        // inherit the proto
    $scope.state.showDecorationDropdownList = false;
    $scope.state.isSaving = false;
    $scope.formData = { 
        decorationTiers: [],
        months: [ 
            { name: 'Jan', value: 0 },
            { name: 'Feb', value: 1 },
            { name: 'Mar', value: 2 },
            { name: 'Apr', value: 3 },
            { name: 'May', value: 4 },
            { name: 'Jun', value: 5 },
            { name: 'Jul', value: 6 },
            { name: 'Aug', value: 7 },
            { name: 'Sep', value: 8 },
            { name: 'Oct', value: 9 },
            { name: 'Nov', value: 10 },
            { name: 'Dec', value: 11 }
        ],
        awardDate: {
            month: 0,
            year: 1975,
        },
        resetAwardDate: resetAwardDate
    };
        
    
	$scope.member = {};
    $scope.award = new ExistingAward();
    
    $scope.submit = saveExistingDecoration;

    $scope.cancelHref = function(){
        if ($scope.member.regt_num){
            return flResource('member').setFragment([$scope.member.regt_num, 'view', 'decorations']).getLink();
        }
        else {
            return flResource('member').getLink();
        }
    };
    
	// Read the url
    if (c.loadWorkflowPath()){
        if ($scope.state.path.id){
            retrieveMember($scope.state.path.id);
            if ($scope.state.path.subId){
                retrieveAward($scope.state.path.id, $scope.state.path.subId).then(function(decorationId){
                    retrieveDecoration(decorationId);
                });
            }
        }
    }
    
    $scope.$watch('formData.awardDate.month', function(newVal){
        if ($scope.award){
            $scope.award.setDateMonth(newVal);
        }
    });
    
    $scope.$watch('formData.awardDate.year', function(newVal){
        if ($scope.award){
            // Range of OK is 1975 –> (this year + 5)
            if (newVal >= 1975 && newVal <= ((new Date).getFullYear() + 5)){
                $scope.award.setDateYear(newVal);
            }
        }
    });
    
    // ====================
    // Function decs
    // ====================
    
	function retrieveMember(memberId){
        return flAPI('member').get([memberId]).then(function(response){
            
            if (response.data && response.data.member){
                $scope.member = response.data.member;
                $scope.state.isMemberLoaded = true;
                return response.data.member.regt_num;
            }
            else {
                throw 'BadRequest.jpg';
            }
            
        }, function(response){
            if (response.status == 404){
                $scope.member.errorNotFound = true;
            }
            else {
                $scope.member.errorServerSide = true;
            }
        });
	}
    
    function retrieveAward(memberId, awardId){
        return flAPI('member').nested('decoration', memberId).get(awardId).then(function(response){
            if (response.data && response.data.memberDecoration){
                c.util.convertToDateObjects(['date', 'created_at', 'updated_at', 'deleted_at'], response.data.memberDecoration);
                $scope.award.id = response.data.memberDecoration.awd_id;
                $scope.award.data = response.data.memberDecoration;
                $scope.formData.awardDate.month = response.data.memberDecoration.date.getMonth();
                $scope.formData.awardDate.year = response.data.memberDecoration.date.getFullYear();
                return response.data.memberDecoration.dec_id;
            }
            else {
                throw 'Failed to get list of decorations';
            }
        }, function(response){
            console.warn(response);
        });
    }
    
    function retrieveDecoration(decorationId){
        return flAPI('decoration').get([decorationId]).then(function(response){
            if (response.data && response.data.decoration){
                c.util.convertToDateObjects(['date_commence', 'date_conclude', 'updated_at'], response.data.decoration);
                $scope.award.existingDecoration = response.data.decoration;
                $scope.award.existingDecorationBadgeUrl = flResource().raw(['/media', 'decoration', response.data.decoration.dec_id, 'badge'], [+new Date]);
            }
            else {
                throw 'Failed to get list of decorations';
            }
        }, function(response){
            console.warn(response);
        });
    }
    
    function saveExistingDecoration(){
        var awardId = $scope.award.id;
        var awardData = $scope.award.data;
        var payload = {
            memberDecoration: awardData
        }; 
        $scope.state.isSaving = true;
        flAPI('member').nested('decoration', $scope.member.regt_num).patch(awardId, payload).then(function(response){
            // Focus on the "Assign another" button
            $scope.award.saved = true;
            setTimeout(function(){
                $scope.$apply(function(){
                    $scope.state.isSaving = false;
                    $scope.state.path.mode = 'view';
                    angular.element('#viewMemberProfileButton').focus();
                });
            }, 300);
        }).catch(function(errorResponse){
            console.warn(errorResponse);
            $scope.state.isSaving = false;
            $scope.award.saveError = true;
        });
    }
    
    function resetAwardDate(){
        var awardDate = $scope.formData.awardDate;
        awardDate.month = (new Date).getMonth();
        awardDate.year = (new Date).getFullYear();
        var award = $scope.award;
        if (award){
            award.setDateMonth(awardDate.month);
            award.setDateYear(awardDate.year);
        }
    }
    
    //======================
    // Classes
    //======================
	
    function ExistingAward(){
        this.saved = false;
        this.saveError = false;
        this.saveDuplicateError = false;
        this.existingDecoration = '';
        this.existingDecorationBadgeUrl = '';
        this.data = {
            dec_id: 0,
            citation: '',
            date: new Date()        // Default to today
        };
        this.setDateMonth = function(month){
            // Always set to first day of the month
            this.data.date.setDate(1);
            this.data.date.setMonth(month);
        };
        this.setDateYear = function(year){
            this.data.date.setFullYear(year);
        };
    }
    
    //======================
    // End Classes
    //======================
});