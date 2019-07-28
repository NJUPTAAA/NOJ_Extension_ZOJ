<?php
namespace App\Babel\Extension\zoj;

use App\Babel\Submit\Curl;
use App\Models\CompilerModel;
use App\Models\JudgerModel;
use App\Models\OJModel;
use Illuminate\Support\Facades\Validator;
use Requests;
use Log;
use Exception;

class Submitter extends Curl
{
    protected $sub;
    public $post_data=[];
    protected $selectedJudger;
    public $oid;

    public function __construct(& $sub, $all_data)
    {
        $this->sub=& $sub;
        $this->post_data=$all_data;
        $judger=new JudgerModel();
        $this->oid=OJModel::oid('zoj');
        if(is_null($this->oid)) {
            throw new Exception("Online Judge Not Found");
        }
        $judger_list=$judger->list($this->oid);
        $this->selectedJudger=$judger_list[array_rand($judger_list)];
    }

    private static function find($pattern, $subject)
    {
        if (preg_match($pattern, $subject, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function _login()
    {
        $params=[
            'handle' => $this->selectedJudger["handle"],
            'password' => $this->selectedJudger["password"],
            'rememberMe' => 'on'
        ];
        $this->login([
            "url"=>'http://acm.zju.edu.cn/onlinejudge/login.do',
            "data"=>http_build_query($params),
            "oj"=>'zoj',
            "handle"=>$this->selectedJudger["handle"]
        ]);
    }

    private function _submit()
    {
        $this->_login();
        $res = Requests::get("http://acm.zju.edu.cn/onlinejudge/showProblem.do?problemCode=".$this->post_data['iid']);
        $submitID = self::find('/problemId=([\s\S]*?)\"><font/',$res->body);
        $params=[
            'languageId' => $this->post_data['lang'],
            'problemId' => $submitID,
            'source' => $this->post_data["solution"],
        ];
        $response=$this->post_data([
            "site"=>"http://acm.zju.edu.cn/onlinejudge/submit.do?problemId=".$submitID,
            "data"=>http_build_query($params),
            "oj"=>"zoj",
            "ret"=>true,
            "follow"=>false,
            "returnHeader"=>true,
            "postJson"=>false,
            "extraHeaders"=>[],
            "handle"=>$this->selectedJudger["handle"]
        ]);
        $this->sub['jid'] = $this->selectedJudger['jid'];
        if (!preg_match('/The submission id is <font color=\'red\'>(\d+)<\/font>/', $response, $match)) {
            $this->sub['verdict'] = "Submission Error";
        } else {
            $this->sub['remote_id']=$match[1];
        }
    }

    public function submit()
    {
        $validator=Validator::make($this->post_data, [
            'pid' => 'required|integer',
            'coid' => 'required|integer',
            'iid' => 'required|integer',
            'solution' => 'required',
        ]);

        if ($validator->fails()) {
            $this->sub['verdict']="System Error";
            return;
        }

        $this->_submit();
    }
}
