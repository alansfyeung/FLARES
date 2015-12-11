// ===============================
//    flaresActivityViewEdit.js
//    View/Edit record
// ===============================

var flaresApp = angular.module('flaresActivityView', ['flaresBase']);

flaresApp.controller('activityViewEditController', function($scope, $window, $location, $controller, flaresAPI, flaresLinkBuilder){
    
    // Add some base 
    var aveController = this;
    // angular.extend(aveController, $controller('baseViewEditController', {$scope: $scope})); 
    // $scope.state = Object.create(aveController.state);     // set parent workflow object as proto
    
    $controller('baseViewEditController', {$scope: $scope}).loadInto(this);
    
    $scope.activity = Object.create($scope.record);
    $scope.originalActivity = Object.create($scope.originalRecord);
    $scope.activityRollStats = {};
    $scope.formData = {};
    
    $scope.edit = function(){
		var sw = $scope.state;
		if (sw.isView()){
			// If in view mode, toggle to Edit mode
			sw.path.mode = 'edit';
			return;
		}
		if (sw.isEdit()){
			// Save the changes
			// send back to view mode
			updateActivityRecord();
			sw.path.mode = 'view';
		}
	};
    $scope.saveEdit = $scope.edit;      // point to the same place
	$scope.cancelEdit = function(){
		if ($scope.state.isLoaded){
			$scope.activity = angular.extend(Object.create($scope.record), $scope.originalActivity);
			$scope.state.path.mode = 'view';
			return;
		}
		console.warn('Cannot cancel - member record was never loaded');
	};
    
    $scope.delete = function(){
        deleteActivity();
    };
	
    // ============
	// Read the url
	if (this.loadWorkflowPath()){
		retrieveActivity();
	}
    
    
    //======================
	// Actions menu
    $scope.actions = {
        markRoll: function(){
            var frag = [$scope.activity.acty_id, 'fill', 'markroll'];
            $window.location.href = flaresLinkBuilder('activity', frag).roll().getLink();
        },
        paradeState: function(){
            var frag = [$scope.activity.acty_id, 'fill', 'paradestate'];
            $window.location.href = flaresLinkBuilder('activity', frag).roll().getLink();
        },
        leave: function(){
            alert('WIP');
        },
        reviewAwol: function(){
            alert('WIP');
        }
    };
    
    //==================
	// Fetch reference data for activityTypes and activityNamePresets
    
    flaresAPI('refData').get(['activity']).then(function(response){
		if (response.data.types){
			$scope.formData.activityTypes = response.data.types;
		}
	});
    

    //======================
	// Save-your-change niceties
	window.onbeforeunload = function(event){
		if ($scope.state.isEdit()){
			var message = 'You are editing this activity record, and will lose any unsaved changes.';
			return message;
		}
	};
    $scope.$on('$destroy', function() {
		delete window.onbeforeunload;
	});
    
    
    // ====================
    // Function decs

    function retrieveActivity(){
		if ($scope.state.path.id){
			flaresAPI('activity').get([$scope.state.path.id]).then(function(response){
				// Process then store in VM
                if (response.data.activity){
                    processActivityRecord(response.data.activity);
                    $scope.state.isActivityLoaded = true;
                }
                else {
                    console.warn('Activity data not loaded');
                }
			}, function(response){
				console.warn(response);
                $scope.state.errorNotLoaded = true;
			});
		}
		else {
			console.warn('Activity ID not specified');
		}
	}
    function processActivityRecord(activity){
        aveController.convertToDateObjects(['start_date', 'end_date', 'created_at', 'updated_at'], activity);
		$scope.activity = activity;
		$scope.originalActivity = angular.extend(Object.create($scope.record), activity);
	}
    function updateActivityRecord(){
		var hasChanges = false;
		var payload = {
			activity: {}
		};	
		angular.forEach($scope.activity, function(value, key){
			if ($scope.originalActivity[key] !== value){
				// Value has changed
				hasChanges = true;
				payload.activity[key] = value;
			}
		});
		if (hasChanges){
			// $http.patch('/api/member/'+$scope.member.regt_num, payload).then(function(response){
			flaresAPI('activity').patch([$scope.activity.acty_id], payload).then(function(response){
				console.log('Save successful');
				$scope.originalActivity = angular.extend(Object.create($scope.originalRecord), $scope.activity);
				
			}, function(response){
				// Save failed. Why?
				alert('Warning: Couldn\'t save this record. Check your internet connection?');
				console.warn('Error: record update', response);
			});
		}
	}
    function deleteActivity(){
        
    }
});

flaresApp.controller('rollBuilderController', function($scope, $filter, $timeout, flaresAPI){
	
    // $scope.roll = []; 
    var rollRefreshPromise;
    $scope.lastError = {};
    $scope.memberList = []; 
    
    // Filter
    $scope.filtering = {
        filters: [],
        activeFilterIndex: '0',
        filterFired: false,
        showing: 0
    };
    $scope.filtering.runFilter = function(){
        var activeFilter = $scope.filtering.filters[0];
        if (this.activeFilterIndex > 0 && this.activeFilterIndex < $scope.filtering.filters.length){
            activeFilter = $scope.filtering.filters[this.activeFilterIndex];
        }
        if (Member.prototype.hasOwnProperty(activeFilter.handler)){
            $scope.memberList.forEach(function(member){
                member[activeFilter.handler](activeFilter.value);
            });
        }
        this.showing = $filter('filter')($scope.memberList, {visible: true}).length;
        this.filterFired = true;
    };

    
    // Quick selection
    $scope.quickSelecting = {
        quickSelections: [],
        activeQuickSelectionIndex: '0',
    };
    $scope.quickSelecting.runQuickSelection = function(){
        
    };
    
    
    $scope.toggleRollSelection = function(member){   
        // console.log(member);
        
        if (member.isMarked()){      // then don't
            return;
        }
        
        member.onRoll = !member.onRoll;
        if (member.delta === 0){
            member.delta = ( member.onRoll ? 1 : -1 );
        }
        else {
            // Delta'd but not yet submitted - revert.
            if ((member.onRoll && member.delta < 0) || (!member.onRoll && member.delta > 0)){
                member.delta = 0;
            }
        }
    };
    
    // Todo: write a last-event timeout which executes processRollDelta()
    // e.g. after 2 sec inactivity

    $scope.bumpRollRefreshTimer = function(){
        $timeout.cancel(rollRefreshPromise);
        rollRefreshPromise = $timeout(function(){
            var activityId = $scope.$parent.activity.acty_id;
            if (activityId){
                processRollDelta(activityId);
                retrieveActivityNominalRoll(activityId).then(function(response){
                    if (response.data.roll){
                        $scope.memberList = mapToMemberList(response.data.roll, $scope.memberList);
                    }
                }, function(){ console.warn('Nominal roll not retrieved') });
            }
        }, 2000);
    };
    
    retrieveRefData();

    retrieveMembers().then(function(response){
        var activityId = $scope.$parent.activity.acty_id;
        if (response.data.members){
            for (var x in response.data.members){
                var newMember = new Member(response.data.members[x]);
                $scope.memberList.push(newMember);
            }
            if (activityId){
                retrieveActivityNominalRoll(activityId).then(function(response){
                    if (response.data.roll){
                        $scope.memberList = mapToMemberList(response.data.roll, $scope.memberList);
                    }
                }, function(){
                    console.warn('Nominal roll not retrieved');
                });
                console.log('Finished loading members, num loaded: ' + response.data.members.length); 
            }
        }
    });
    
    // $scope.$watch('$parent.activity.acty_id', function(){
    	// retrieveActivityNominalRoll();
    // });
    
    
    
    // ======================
    // Function decs
    
    // ----------------
    //  Member object
    // ----------------
    function Member(memberData){
        this.visible = true;
        this.onRoll = false;
        this.roll = null;
        this.delta = 0;
        this.data = memberData;
    }
    Member.prototype.associateRoll = function(rollEntry){
        this.onRoll = true;
        this.roll = rollEntry;
    };
    Member.prototype.displayStatus = function(){
        // Todo: return if they are on leave during this period
        if (this.roll){
            if (this.roll.recorded_value !== '0'){
                return 'Marked ['+this.roll.recorded_value+']';
            }
        }
        return 'Ready';
    };
    Member.prototype.isMarked = function(){
        return (this.roll && this.roll.recorded_value !== '0');
    };
    Member.prototype.defilter = function(){
        this.visible = true;
    };
    Member.prototype.selectedFilter = function(){
        this.visible = !!this.onRoll;
    };
    Member.prototype.unselectedFilter = function(){
        this.visible = !this.onRoll;
    };
    Member.prototype.platoonFilter = function(platoon){
        this.visible = this.data.current_platoon && this.data.current_platoon.platoon === platoon;
    }
    Member.prototype.rankFilter = function(rank){
        this.visible = this.data.current_rank && this.data.current_rank.rank === rank;
    }
    
    // --------------------------------
    //  Filter & quick select objects
    // --------------------------------
    function Filter(type, value, desc, handler){  
        this.type = type || 'other';
        this.value = value || null;
        this.desc = desc || 'All';
        this.handler = handler || 'defilter';        // as string representing the Member.prototype.xxxxFilter
    }
    function QuickSelection(type, value, desc, handler){
        this.type = type || 'other';
        this.value = value || null;
        this.desc = desc || 'All';
        this.handler = handler || 'defilter';        // as string representing the Member.prototype.xxxxFilter
    }

    function retrieveRefData(){
        flaresAPI('refData').getAll().then(function(response){
            if (response.data.platoons){
                $scope.formData.platoons = response.data.platoons;
            }
            if (response.data.ranks){
                $scope.formData.ranks = response.data.ranks;
            }
            
            $scope.filtering.filters = buildFilters();
            // $scope.filtering.activeFilterIndex = '0';        // Set an initial one
        });
    }
    
    function retrieveMembers(){
        return flaresAPI('member').getAll();
    }
    
    function retrieveActivityNominalRoll(activityId){
        return flaresAPI('activity').rollFor(activityId).getAll();
    }

    function mapToMemberList(roll, members){
        // For each roll entry, find the corresponding member
        // for (var x in roll){
        roll.forEach(function(rollEntry){
            members.forEach(function(member, index){
                if (member.data.regt_num === rollEntry.regt_num){
                    member.associateRoll(rollEntry);
                    return true;
                }
            });
        });
        return members;
    }
    
    // Periodically read the roll deltas and process as required
    function processRollDelta(activityId){
        var deletes = [];
        var adds = [];
        $scope.memberList.forEach(function(member, index){
            if (member.delta < 0){
                // This is delete delta
                if (member.roll){
                    deletes.push(member.roll.att_id);
                    member.delta = 0;                    
                }
            }
            if (member.delta > 0){
                // This is an add delta
                adds.push({regt_num: member.data.regt_num});
                member.delta = 0;
            }
        });
        
        if (adds.length > 0){
            var payloadAdd = {
                attendance: adds
            };
            flaresAPI('activity').rollFor(activityId).post([], payloadAdd).then(function(response){
                $scope.lastError = response.data.error;
                console.log('added', response);
            });            
        }
        
        deletes.forEach(function(rollId){
            flaresAPI('activity').rollFor(activityId).delete(rollId).then(function(response){
                $scope.lastError = response.data.error;
                console.log('deleted', response);
            });
        });
    }
    
    // Filters and quick search
    function buildFilters(){
        var filters = [];
        // Add some of our own
        filters.push(new Filter());
        filters.push(new Filter('other', null, 'Not already on the roll', 'unselectedFilter'));
        filters.push(new Filter('other', null, 'Already on the roll', 'selectedFilter'));
        // Add platoons
        if ($scope.formData.platoons){      
            $scope.formData.platoons.forEach(function(pl){
                filters.push(new Filter('platoon', pl.abbr, 'Platoon: ' + pl.name, 'platoonFilter'));
            });
        }
        // Add ranks
        if ($scope.formData.ranks){        
            $scope.formData.ranks.forEach(function(rank){
                filters.push(new Filter('rank', rank.abbr, 'Rank: ' + rank.name, 'rankFilter'));
            });
        }
        return filters;
    };

});


flaresApp.controller('permissionController', function($scope, flaresAPI){
    
    
});