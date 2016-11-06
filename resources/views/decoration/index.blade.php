{{-- Search all members --}}
@extends('primary')

@section('ng-app', 'flaresDecoration')
@section('ng-controller', 'indexController')
@section('title', 'All Decorations')

@section('heading')
<h1>All decorations</h1>
@endsection

@push('scripts')
<script src="/app/components/decoration/flaresDecorationIndex.js"></script>
@endpush
@push('vendor-scripts')
<script src="/assets/js/flow/ng-flow-standalone.min.js"></script>
@endpush

@section('content')
@verbatim
<section class="index">
    <div class="alert alert-warning" ng-if="decorations.length === 0">
        <strong>No decorations found:</strong> Consider <a ng-href="{{gotoCreateNew}}">creating a new decoration</a>
    </div>
    <table class="table table-hover" ng-show="decorations.length > 0">
        <colgroup>
            <col style="width: 140px;">
            <col style="width: 40px;">
            <col>
        </colgroup>
        <thead>
            <tr>
                <th>Badge</th>
                <th>Tier</th>
                <th>Name</th>
            </tr>
        </thead>
        <tbody>
            <tr ng-repeat="dec in decorations" ng-click="selectDecoration(dec)">
                <td><span class="fl-dec-badge smaller"><img ng-src="{{badgeSrc(dec)}}"></span></td>
                <td>{{dec.tier}}</td>
                <td>{{dec.name}}</td>
            </tr>
        </tbody>
    </table>
</section>
@endverbatim
@endsection
