<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\GoalsRequest;
use App\Models\Goals;
use App\Models\MealPlanner;
use App\Models\UserDayLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use JWTAuth;
use Auth;

/**
 * Class GoalController
 * @package App\Http\Controllers\Api
 */
class GoalController extends Controller
{
    /**
     * @SWG\Post(
     *     path="/goals",
     *     tags={"goals"},
     *     description="Operations about goals",
     *     summary="Create new goal",
     *     operationId="createGoal",
     *     consumes={"application/x-www-form-urlencoded"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="step",
     *         description="Step of goal creation",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="gender",
     *         description="Gender. <br> 0 - Man <br> 1 - Woman",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="age",
     *         description="Age",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="height",
     *         description="Height (cm)",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="weight",
     *         description="Weight(kg)",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="life",
     *         description="type_life. <br> Values: 0, 1, 2",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="child",
     *         description="children. <br> Values: 0, 1",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="dog",
     *         description="dog. <br> Values: 0, 1",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="house",
     *         description="type_house. <br> Values: 0, 1, 2",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="movement",
     *         description="type_movement. <br> Values: 0, 1, 2",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="work_environment",
     *         description="work_environment. <br> Values: 0, 1, 2, 3",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="exercise",
     *         description="exercise_count. <br> Values: 0, 1, 2, 3",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="type_goal",
     *         description="Type goal. <br> 0 - Loose weight <br> 1 - Keep weight",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="level",
     *         description="level. <br> Values between 500..714",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="goal_weight",
     *         description="My goal weight is.",
     *         in="formData",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=400,
     *         description="Http bad request"
     *     )
     * )
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        // check if active goal exists
        $countActiveGoal = Goals::where('users_id', '=', $user->id)
            ->where('state_goal', '=', Goals::STATE_ACTIVE)
            ->count();

        if ($countActiveGoal > 0) {
            return self::jsonErrorValidation(['state_goal' => trans('goals.error_active_goal_exists')]);
        }

        // check if previous steps fills
        $goal = Goals::where('users_id', '=', $user->id)
            ->where('state_goal', '=', Goals::STATE_DRAFT)
            ->first();

        // validate params
        $goalsRequest = new GoalsRequest();
        if ($goal) {
            $goalsRequest->setStartWeight($goal->weight);
        }
        $rules = $goalsRequest->rules();

        $params = $request->all();
        unset($params['token']);
        $params['users_id'] = $user->id;

        $created = new Carbon(Carbon::now()->toDateTimeString());
        $params['created_at'] = $created->getTimestamp();
        $params['updated_at'] = $created->getTimestamp();

        $validate = $this->apiValidate($params, $rules);

        if ($validate !== true) {
            return self::jsonErrorValidation($validate);
        };

        if (($params['step'] == Goals::FINAL_STEP_CREATION_GOAL || $params['step'] == Goals::SECOND_STEP_CREATION_GOAL)
            && !$goal) {
            return self::jsonErrorValidation(['state_goal' => trans('goals.error_previous_steps')]);
        }

        // save goal
        $goal = Goals::saveGoal($goal, $params, $created);

        return response()->json(
            $goal
        );
    }
   
    /**
     * @SWG\Patch(
     *     path="/goals/{id}",
     *     description="Edit user's goal",
     *     summary="Edit user's goal",
     *     tags={"goals"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         description="Goal id",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="gender",
     *         description="Gender. <br> 0 - Man <br> 1 - Woman",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="age",
     *         description="Age",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="height",
     *         description="Height (cm)",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="weight",
     *         description="Weight(kg)",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="life",
     *         description="type_life. <br> Values: 0, 1, 2",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="child",
     *         description="children. <br> Values: 0, 1",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="dog",
     *         description="dog. <br> Values: 0, 1",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="house",
     *         description="type_house. <br> Values: 0, 1, 2",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="movement",
     *         description="type_movement. <br> Values: 0, 1, 2",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="work_environment",
     *         description="work_environment. <br> Values: 600, 770, 1400, 1750",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="exercise",
     *         description="exercise_count. <br> Values: 110, 170, 210",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="type_goal",
     *         description="Type goal. <br> 0 - Loose weight <br> 1 - Keep weight",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="level",
     *         description="level. <br> Values between 500..714",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="goal_weight",
     *         description="My goal weight is.",
     *         in="formData",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Validation error"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Success response"
     *    )
     * )
     */
    public function update($id, Request $request)
    {
        $goal = Goals::findOrFail($id);
        
        $params = $request->all();

        $rules = [
            'gender' => 'required|in:' . join(',', Goals::getGenderTypesIds()),
            'age' => 'required|integer|digits_between:1,3|between:10,100',
            'height' => 'required|integer|between:' . Goals::MIN_HEIGHT . ',' . Goals::MAX_HEIGHT,
            'weight' => 'required|numeric|between:' . Goals::MIN_WEIGHT . ',' . Goals::MAX_WEIGHT,
            'life' => 'required|in:' . join(',', Goals::getLiveTypesIds()),
            'child' => 'required|in:' . join(',', Goals::getNoYesIds()),
            'dog' => 'required|in:' . join(',', Goals::getNoYesIds()),
            'house' => 'required|in:' . join(',', Goals::getHouseTypesIds()),
            'movement' => 'required|in:' . join(',', Goals::getMovementTypesIds()),
            'work_environment' => 'required|in:' . join(',', Goals::getWorkEnvironmentIds()),
            'exercise' => 'required|in:' . join(',', Goals::getExerciseCountIds()),
            'type_goal' => 'required|in:' . join(',', Goals::getTypesIds()),
            'level' => 'required|integer|between:' . Goals::MIN_LEVEL . ',' . Goals::MAX_LEVEL,
            'goal_weight' => 'required|numeric|between:'
                . Goals::MIN_WEIGHT . ',' . Goals::MAX_WEIGHT . '|max:' . Goals::MAX_WEIGHT
        ];
        
        $validate = $this->apiValidate($params, $rules);

        if ($validate !== true) {
            return self::jsonErrorValidation($validate);
        };
        
        $goal->update($params);
        
        return self::jsonSuccessStatus(trans('goals.success_update_goal'));
    }
     
    /**
     *  @SWG\Get(
     *     path="/goals",
     *     description="Get list of user's goals",
     *     summary="Get list of user's goals",
     *     tags={"goals"},
     *     operationId="getUserGoals",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=400,
     *         description="Http bad request"
     *  )
     * )
     */
    public function getUserGoals()
    {
        $user = Auth::user();

        $activeGoals = Goals::getActiveGoal($user->id);
        if ($activeGoals) {
            $res = Goals::getLastWeightLog($user->id, $activeGoals->created_at->toDateString());
            $activeGoals['last_weight_log'] = $res ? $res->weight : $activeGoals->weight;
        }

        $previousGoals = Goals::getPreviousGoals($user->id);

        return response()->json([
            'active_goal' => $activeGoals,
            'previous_goals' => $previousGoals
        ]);
    }

    /**
     *  @SWG\Patch(
     *     path="/goals/current/close",
     *     description="Operations about goals",
     *     summary="Close active goal",
     *     tags={"goals"},
     *     @SWG\Response(
     *         response=400,
     *         description="Http bad request"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Status success"
     *     )
     *   )
     * )
     */
    public function closeCurrentGoal()
    {
        $user = Auth::user();

        $res = Goals::where('users_id', '=', $user->id)
            ->where('state_goal', '=', Goals::STATE_ACTIVE)
            ->update(['state_goal' => Goals::STATE_CLOSE]);

        if (!$res) {
            return self::jsonError(trans('goals.goal_not_found'));
        }

        return self::jsonSuccessStatus(trans('goals.success_update_goal'));
    }

    /**
     *  @SWG\Get(
     *     path="/goal/draft",
     *     description="Operations about goals",
     *     summary="Get draft goal for current user",
     *     tags={"goals"},
     *     operationId="getDraftGoals",
     *     produces={"application/json"},
     *     @SWG\Response(
     *         response=400,
     *         description="Http bad request"
     *     )
     * )
     */
    public function getDraftGoal()
    {
        $user = Auth::user();

        return response()->json(Goals::getDraftGoal($user->id));
    }

    /**
     *  @SWG\Get(
     *     path="/goal/{id}",
     *     description="Operations about goals",
     *     summary="Get user's goal",
     *     tags={"goals"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         description="Goal Identifier",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Goal response"
     *     )
     * )
     */
    public function getUserGoal($id)
    {
        $userId = Auth::user()->id;
        $active_goal = Goals::getActiveGoal($userId);
        
        $data = null;
        if (!empty($active_goal)) {
            $data = MealPlanner::getChartData($active_goal, $userId);
        }
        
        return response()->json($data);
    }

    /**
     *  @SWG\Delete(
     *     path="/goals/{id}",
     *     description="Operations about goals",
     *     summary="Delete a goal",
     *     tags={"goals"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         description="Goal Identifier",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=400,
     *         description="Goal not found"
     *     )
     * )
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        $user = Auth::user();

        $goal = Goals::where('id', '=', $id)
            ->where('users_id', '=', $user->id)->first();

        if (is_null($goal)) {
            return self::jsonError(trans('goals.goal_not_found'));
        }

        $goal->delete();

        return self::jsonSuccessStatus(trans('goals.success_delete_goal'));
    }
}
