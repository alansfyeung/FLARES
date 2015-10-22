<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Activity;
use App\Http\Requests;
use App\Http\Controllers\Controller;

class ActivityController extends Controller
{
	const ERR_POSTDATA_MISSING = 4001;
	const ERR_POSTDATA_FORMAT = 4002;
	const ERR_HAS_ROLL = 4100;
	const ERR_EX = 5000;
	const ERR_DB_PERSIST = 5001;
	
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $activities = Activity::select()->orderBy('created_at', 'desc')->get();
        return response()->json([
            'activities' => $activities->toArray()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
		$recordId = 0;
		
		if ($request->has('activity')){
			$postDataActivity = $request->input('activity', []);
			try {
				$activity = Activity::create($postDataActivity);
				$recordId = $activity->acty_id;
			} 
			catch (\Exception $ex){
				return response()->json([
                    'error' => ['code' => $ex->getCode(), 'reason' => $ex->getMessage()]
                ]);
			}
		} 
		else {
			return response()->json([
                'error' => ['code' => self::ERR_POSTDATA_FORMAT, 'reason' => 'Post data not provided in required format']
            ], 400);
		}
		
		return response()->json([
			'recordId' => $recordId
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Request $request, $id)
    {
		// detail -- [ high | med | low ]
		$detailLevel = $request->input('detail', 'low');
        try {
            if ($detailLevel == 'high'){	
                $activity = Activity::with('attendances')->firstOrFail($id);
            }
            else {
                $activity = Activity::findOrFail($id);
            }
            return response()->json([
                'activity' => $activity->toArray()
            ]);
        }
        catch (\Exception $ex){
            return response()->json([
                'error' => ['code' => $ex->getCode(), 'reason' => $ex->getMessage()]
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
		$updated = 0;
		$error = [];
		
		try {
			if ($request->has('activity')){
				$postDataUpdate = $request->input('activity', []);
				$updated = Activity::findOrFail($id)->fill($postDataUpdate)->save();
			}
			else {
				throw new \Exception('No activity values in post data', self::ERR_POSTDATA_MISSING);
			}
            return response()->json([
                'recordId' => $updated
            ]);            
		}
		catch (\Exception $ex){
			return response()->json([
                'error' => ['code' => $ex->getCode(), 'reason' => $ex->getMessage()]
            ], 500);
		}
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
		$deleted = 0;
		
		try {
			$activity = Activity::findOrFail($id);
			if ($activity->attendances->count() > 0){
				throw new \Exception('Attendance records exist, cannot delete this activity', self::ERR_HAS_ROLL);
			}
			else {
				$deleted = $activity->delete();
			}
            return response()->json(['success' => $deleted]);
		}
		catch (\Exception $ex){
			return response()->json([
                'error' => ['code' => $ex->getCode(), 'reason' => $ex->getMessage()]
            ], 403);
		}
    }
	
}
