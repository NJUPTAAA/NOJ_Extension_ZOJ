<?php
namespace App\Babel\Extension\zoj;

use App\Babel\Submit\Curl;
use App\Models\Submission\SubmissionModel;
use App\Models\JudgerModel;
use Requests;
use Exception;
use Log;

class Judger extends Curl
{

    public $verdict=[
        'Accepted'=>"Accepted",
        "Presentation Error"=>"Presentation Error",
        'Time Limit Exceeded'=>"Time Limit Exceed",
        "Memory Limit Exceeded"=>"Memory Limit Exceed",
        'Wrong Answer'=>"Wrong Answer",
        'Segmentation Error'=>"Runtime Error",
        'Non-zero Exit Code'=>'Runtime Error',
        'Floating Point Error'=>'Runtime Error',
        'Output Limit Exceeded'=>"Output Limit Exceeded",
        'Compilation Error'=>"Compile Error",
    ];
    private $model=[];

    public function __construct()
    {
        $this->model["submissionModel"]=new SubmissionModel();
        $this->model["judgerModel"]=new JudgerModel();
    }

    public function judge($row)
    {
        $sub = [];
        $response = Requests::get("http://acm.zju.edu.cn/onlinejudge/showRuns.do?contestId=1&idEnd=".$row['remote_id']);
        preg_match ('/<td class="runId">[\s\S]*?judgeReply[\s\S]*?">([\s\S]*?)<\/span>[\s\S]*?runTime">([\s\S]*?)<\/td>[\s\S]*?runMemory">([\s\S]*?)<\/td>/', $response->body, $matches);
        $sub['verdict'] = $this->verdict[trim(strip_tags($matches[1]))];
        $sub['remote_id'] = $row['remote_id'];
        $sub['time'] = intval($matches[2]);
        $sub['memory'] = intval($matches[3]);

        // Seems ZOJ no longer provides Compile Info.
        // if($sub['verdict'] == 'Compile Error') {
        //     $ret = Requests::get("http://acm.zju.edu.cn/onlinejudge/showJudgeComment.do?submissionId=".$row['remote_id']);
        //     $sub['compile_info'] = $ret->body;
        // }

        $this->model["submissionModel"]->updateSubmission($row['sid'], $sub);
    }
}
