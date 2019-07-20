<?php
namespace App\Babel\Extension\zoj;//The 'template' should be replaced by the real oj code.

use App\Babel\Crawl\CrawlerBase;
use App\Models\ProblemModel;
use App\Models\OJModel;
use KubAT\PhpSimple\HtmlDomParser;
use Requests;
use Exception;

class Crawler extends CrawlerBase
{
    public $oid=null;
    public $prefix="ZOJ";
    private $con;
    private $imgi;
    private $action;
    private $cached;
    /**
     * Initial
     *
     * @return Response
     */
    public function start($conf)
    {
        $this->action=isset($conf["action"])?$conf["action"]:'crawl_problem';
        $con=isset($conf["con"])?$conf["con"]:'all';
        $this->cached=isset($conf["cached"])?$conf["cached"]:false;
        $this->oid=OJModel::oid('zoj');

        if(is_null($this->oid)) {
            throw new Exception("Online Judge Not Found");
        }

        if ($this->action=='judge_level') {
            $this->judge_level();
        } else {
            $this->crawl($con);
        }
    }

    public function judge_level()
    {
        // TODO
    }

    private static function find($pattern, $subject)
    {
        if (preg_match($pattern, $subject, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function cacheImage($dom)
    {
        if(!$dom) return $dom;
        foreach ($dom->find('img') as $ele) {
            $src=str_replace('\\', '/', $ele->src);
            if (strpos($src, '://')!==false) {
                $url=$src;
            } elseif ($src[0]=='/') {
                $url='http://acm.zju.edu.cn/onlinejudge'.$src;
            } else {
                $url='http://acm.zju.edu.cn/onlinejudge/'.$src;
            }
            $res=Requests::get($url, ['Referer' => 'http://acm.zju.edu.cn/onlinejudge']);
            $ext=['image/jpeg'=>'.jpg', 'image/png'=>'.png', 'image/gif'=>'.gif', 'image/bmp'=>'.bmp'];
            $pos=strpos($ele->src, '.',-1);
            if ($pos===false) {
                $cext='';
            } else {
                $cext=substr($ele->src, $pos);
            }
            $fn=$this->con.'_'.($this->imgi++).$cext;
            $dir=base_path("public/external/zoj/img");
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents(base_path("public/external/zoj/img/$fn"), $res->body);
            $ele->src='/external/zoj/img/'.$fn;
        }
        return $dom;
    }

    public function crawl($con) 
    {
        if($con == "all") {
            $lastProbID = 4137; // Too hard to get the explicit ID;
            foreach(range(1001, $lastProbID) as $probID) {
                $this->_crawl($probID);
            }
        } else {
            $this->_crawl($con);
        }
    }

    protected function _crawl($con, $retry=1)
    {
        $attempts=1;
        while($attempts <= $retry){
            try{
                $this->crawlProblem($con);
            }catch(Exception $e){
                $attempts++;
                continue;
            }
            break;
        }
    }

    public function crawlProblem($con)
    {
        $this->_resetPro();
        $this->con = $con;
        $this->imgi = 1;
        $problemModel=new ProblemModel();
        if(!empty($problemModel->basic($problemModel->pid($this->prefix.$con))) && $this->action=="update_problem"){
            return;
        }
        if($this->action=="crawl_problem") $this->line("<fg=yellow>Crawling:   </>{$this->prefix}{$con}");
        elseif($this->action=="update_problem") $this->line("<fg=yellow>Updating:   </>{$this->prefix}{$con}");
        else return;
        $res=Requests::get("http://acm.zju.edu.cn/onlinejudge/showProblem.do?problemCode={$con}");
        if (strpos($res->body, 'No such problem.')!==false) {
            $this->line("\n  <bg=red;fg=white> Exception </> : <fg=yellow>Can not find problem.</>\n");
            throw new Exception("Can not find problem");
        }
        $this->pro['pcode']=$this->prefix.$con;
        $this->pro['OJ']=$this->oid;
        $this->pro['contest_id']=null;
        $this->pro['index_id']=$con;
        $this->pro['origin']="http://http://acm.zju.edu.cn/onlinejudge/showProblem.do?problemCode={$con}";
        
        $this->pro['title']=self::find('/<span class="bigProblemTitle">([\s\S]*?)<\/span>/',$res->body);
        $this->pro['time_limit'] = intval(self::find('/Time Limit: <\/font> ([\s\S]*?) Second/',$res->body))*1000;
        $this->pro['memory_limit'] = self::find('/Memory Limit: <\/font> ([\s\S]*?) KB/',$res->body);
        $this->pro['solved_count'] = 0;
        $this->pro['input_type'] = 'standard input';
        $this->pro['output_type'] = 'standard output';
        if(strpos($res->body, "Input</") != false) {
            $this->pro['description'] = strip_tags($this->cacheImage(HtmlDomParser::str_get_html(self::find("/KB[\s\S]*<hr>([\s\S]*?)>[\s]*Input/",$res->body), true, true, DEFAULT_TARGET_CHARSET, false)));
            $this->pro['input'] = strip_tags(self::find('/>[\s]*Input([\s\S]*?)>[\s]*Out?put/',$res->body));
            $this->pro['output'] = strip_tags(self::find('/>[\s]*Out?put([\s\S]*?)>[\s]*Sample Input/',$res->body));
            $this->pro['sample'] = [];
            $sample_output = trim(strip_tags(self::find("/>Sample Out?put([\s\S]*?)<hr/",$res->body)));
            if(strpos($sample_output,"Hint") != false) {
                $length = strpos($sample_output,"Hint");
                $sample_output = substr($sample_output,0,$length);
            }
            $this->pro['sample'][] = [
                'sample_input'=>trim(strip_tags(self::find("/>[\s]*Sample Input([\s\S]*?)>[\s]*(Sample Out?put|Output for the Sample Input)/",$res->body))),
                'sample_output'=>$sample_output
            ];
        } else {
            $this->line("\n  <bg=yellow;fg=black> Warning </> : <fg=red>Missing information.</>\n");
            $this->pro['description'] = $this->cacheImage(HtmlDomParser::str_get_html(self::find("/KB[\s\S]*<hr>([\s\S]*?)<hr>/",$res->body), true, true, DEFAULT_TARGET_CHARSET, false));
            $this->pro['input'] = "";
            $this->pro['output'] = "";
            $this->pro["sample"] = [];
        }
        $this->pro['note'] = strip_tags(self::find('/Hint([\s\S]*?)<hr/',$res->body));
        $this->pro['source'] = strip_tags(self::find('/Source:\s*<strong>([\s\S]*?)<\/strong><br>/',$res->body));
        if($this->pro['source'] === "") {
            $this->pro['source'] = $this->pro['pcode'];
        }
        $problem=$problemModel->pid($this->pro['pcode']);

        if ($problem) {
            $problemModel->clearTags($problem);
            $new_pid=$this->updateProblem($this->oid);
        } else {
            $new_pid=$this->insertProblem($this->oid);
        }

        if($this->action=="crawl_problem") $this->line("<fg=green>Crawled:    </>{$this->prefix}{$con}");
        elseif($this->action=="update_problem") $this->line("<fg=green>Updated:    </>{$this->prefix}{$con}");
    }
}
