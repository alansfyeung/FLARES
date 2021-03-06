<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use App\DecorationApproval;
use App\Member;
use App\MemberDecoration;
use Illuminate\Http\Request;
use App\Http\Custom\ResponseCodes;

class DecorationApprovalController extends Controller
{
    /**
     * Display all approvals regardless of decision.
     * The list repsonse will include eager-loaded relations.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = DecorationApproval::with(['approver', 'requested_decoration']);

        if (!empty($request->query('forumsUsername'))) {
            $forumsUsername = strtolower((string) $request->query('forumsUsername'));
            $member = Member::where('forums_username', $forumsUsername)->firstOrFail();
            $query->where('regt_num', $member->regt_num);
            $query->orderBy('created_at', 'desc');
        } else {
            $query->with('requester');      // was omitted from forumsUsername request because you already know who it is.
        }

        switch ($request->query('status')) {
            case 'pending':
                $query->whereNull('is_approved');       // Return only non-decided records.
                break;
            case 'approved':
                $query->where('is_approved', '>=', 1);
                break;
            case 'declined':
            case 'rejected':
                $query->where('is_approved', 0);
                break;
            case 'history':
                $query->whereNotNull('is_approved');
                break;
            default: 
                // none
                break;
        }

        $limit = intval($request->query('limit'));
        $offset = intval($request->query('offset'));
        if ($limit > 0){
            $query->take($limit);
        }
        if ($offset > 0){
            $query->skip($offset);
        }

        return response()->json([
            'approvals' => $query->get()
        ]);
    }

    /**
     * Create a new approval request. This will usually be initiated from a 3rd party. 
     * We need to verify that the member and decoration both exist first, before creating the approval. 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate existence of member. If required, resolve it from the forums username. 
        try {
            if ($request->has('member')) {
                $memberData = $request->input('member');    // expect either regt_num or forums_username as keys. 
                if (!empty($memberData['regt_num'])) {
                    $member = Member::findOrFail($memberData['regt_num']);
                } elseif (!empty($memberData['forums_username'])) {
                    $member = Member::where('forums_username', $memberData['forums_username'])->firstOrFail();
                } else {
                    throw new \Exception('Could not find a way to resolve the member', ResponseCodes::ERR_POSTDATA_MISSING);
                }
            }
        } catch (\Exception $ex) {
            $httpErrorCode = ($ex->getCode() == ResponseCodes::ERR_POSTDATA_MISSING ? 400 : 500 );
            return response()->json([
                'error' => ['code' => $ex->getCode(), 'reason' => $ex->getMessage()]
            ], $httpErrorCode);
        }

        try {
			if ($request->has('approval')) {
                $postData = $request->input('approval');
                // Need to remove any inadvertent approval fields ie:
                // is_approved, user_id, decision_date, justification
                if (array_key_exists('is_approved', $postData) || array_key_exists('user_id', $postData) 
                    || array_key_exists('decision_date', $postData) || array_key_exists('justification', $postData)) {
                    throw new \Exception('Contained prohibited fields: is_approved, user_id, decision_date and/or justification', ResponseCodes::ERR_POSTDATA_FORMAT);
                } else {
                    $newApproval = $member->decoration_approvals()->create($postData);
                    $newApprovalId = $newApproval->dec_appr_id;
                    // $newApproval = DecorationApproval::updateOrCreate(['dec_appr_id' => $id], $approvalData);
                }
			} else {
				throw new \Exception('Post data incorrect format', ResponseCodes::ERR_POSTDATA_FORMAT);
			}
			return response()->json([
                'id' => $newApprovalId
            ]);
		} catch (\Exception $ex) {
            $httpErrorCode = ($ex->getCode() == ResponseCodes::ERR_POSTDATA_FORMAT ? 400 : 500 );
            return response()->json([
                'error' => ['code' => $ex->getCode(), 'reason' => $ex->getMessage()]
            ], $httpErrorCode);
		}
    }

    /**
     * Get an approval. 
     * Does not include any eager-loaded relationships.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // $approval = DecorationApproval::with(['requested_decoration', 'requester', 'approver'])->firstOrFail($id);
        $approval = DecorationApproval::findOrFail($id);
        $approval->load(['requested_decoration', 'requester', 'approver']);
        return response()->json([
            'approval' => $approval,
            'requested_decoration' => $approval->requested_decoration,
            'requester' => $approval->requester,
            'approver' => $approval->approver,
		]);
    }

    /**
     * Update with an approval. If it was accepted, this also needs to create an award record. 
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            if ($request->has('approval')) {
                $postData = $request->input('approval');
                // If it contains regt_num/dec_id etc, then error. Those fields should be immutable after creation
                if (array_key_exists('regt_num', $postData) || array_key_exists('dec_id', $postData) || array_key_exists('request_comment', $postData)) {
                    throw new \Exception('Contained prohibited fields: regt_num, dec_id and/or request_comment', ResponseCodes::ERR_POSTDATA_FORMAT);
                }
                // Make sure that the approval has got a decision, and a justification if required. 
                if (array_key_exists('is_approved', $postData) && !is_null($postData['is_approved'])) {
                    if ((string) $postData['is_approved'] == '0' && (!array_key_exists('justification', $postData) || empty($postData['justification']))) {
                        throw new \Exception('Missing a justification for an declined decision', ResponseCodes::ERR_POSTDATA_MISSING);        
                    }
                    $isRequestApproved = (int) $postData['is_approved'] > 0;
                } else {
                    throw new \Exception('Missing an approval decision', ResponseCodes::ERR_POSTDATA_MISSING);    
                }

                // Resolve the admin user who is making the request. 
                $postData['user_id'] = Auth::id();
                $postData['decision_date'] = date('Y-m-d');
                $updatedApproval = DecorationApproval::findOrFail($id);
                $updatedApproval->is_approved = $postData['is_approved'];       // Need to save it as 0 or 1
                $updatedApproval->justification = $postData['justification'];
                $updatedApproval->user_id = $postData['user_id'];
                $updatedApproval->decision_date = $postData['decision_date'];
                $updatedApproval->save();
                $updatedApprovalId = $updatedApproval->dec_appr_id;
                // Note: Preserve the date on the original request, but update the date for the real request

                if ($isRequestApproved) {
                    // Create an award record based off this info 
                    $award = new MemberDecoration();
                    $award->regt_num = $updatedApproval['regt_num'];
                    $award->dec_id = $updatedApproval['dec_id'];
                    if (!empty($postData['date'])) {
                        $award->date = date('Y-m-d', strtotime($postData['date']));
                    } elseif (!empty($updatedApproval['date'])) {
                        $award->date = date('Y-m-d', strtotime($updatedApproval['date']));
                    }
                    if (!empty($postData['citation'])) {
                        $award->citation = $postData['citation'];
                    }
                    $award->user_id = Auth::id();       // The currently logged in admin user
                    $award->dec_appr_id = $updatedApprovalId;       // Link to the decorationapproval
                    
                    // Save it
                    $award->save();
                    $awardId = $award->awd_id;
                }

			} else {
				throw new \Exception('Post data incorrect format', ResponseCodes::ERR_POSTDATA_FORMAT);
            }

            DB::commit();
			return response()->json([
                'id' => $updatedApprovalId,
                'memberDecorationId' => !empty($awardId) ? $awardId : null,
            ]);
		} catch (\Exception $ex) {
            DB::rollBack();

            // Check for an integrity issue it's probably a duplicate award. 
            if ($ex->getCode() == '23000') {
                // SQLSTATE[23000]: Integrity constraint violation
                return response()->json([
                    'error' => ['code' => ResponseCodes::ERR_DECORATION_ALREADY_ASSIGNED, 'reason' => 'Decoration was already assigned to the member']
                ], 500);
            } else {
                return response()->json([
                    'error' => ['code' => $ex->getCode(), 'reason' => $ex->getMessage()]
                ], 500);
            }
		}
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        throw new \Exception("Decoration approvals cannot be deleted", ResponseCodes::ERR_OP_UNAVAILABLE);
    }
}
