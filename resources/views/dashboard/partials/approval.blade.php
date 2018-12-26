{{-- Approval list for all decorations, for insertion into dashboard --}}
<section ng-if="state.approvalsLoaded">
    <div class="alert alert-success" ng-hide="state.approvalsRemaining">
        <strong>No pending approvals.</strong> All requests have been processed.
    </div>
    
    <table class="table table-striped" ng-show="state.approvalsRemaining">
        <colgroup>
            <col>
            <col>
            <col style="width: 120px;">
            <col style="width: 140px;">
            <col style="width: 60px;">
        </colgroup>
        <thead>
            <tr>
                <td>Requester</td>
                <td>Decoration</td>
                <td>Date lodged</td>
                <td>Status</td>
                <td></td>
            </tr>
        </thead>
        <tbody>
            @verbatim
            <tr ng-repeat="appr in approvals" class="dashboard-approval-row" ng-click="selectApproval(appr)">
                <td>{{approval.requester}}</td>
                <td>{{approval.requestedDecoration.name}}</td>
                <td>{{approval.created_at | date:'shortDate'}}</td>
                <td>{{approval.statusName}}</td>
                <td>
                    <a class="btn btn-default btn-block btn-xs" target="_blank" ng-click="$event.stopPropagation()"
                        ng-href="{{ route('approval::approve-decoration') }}#!/@{{approval.dec_appr_id}}/edit">
                        <span class="glyphicon glyphicon-share text-muted"></span>
                    </a>
                </td>
            </tr>
            @endverbatim
        </tbody>
    </table>
</section>
<div class="alert alert-info" ng-hide="state.approvalsLoaded">Loading pending approvals&hellip;</div>