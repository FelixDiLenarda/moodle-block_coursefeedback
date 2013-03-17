<?php
	defined('MOODLE_INTERNAL') || die();
	require_once($CFG->dirroot.'/blocks/coursefeedback/lib/lib.php');

	class feedbackexport
	{
		protected $course    = 0;
		protected $filetypes = array('csv');
		private $content     = '';
		private $format;
		
		public function __construct($course = 0,$seperator = "\t")
		{
			global $DB;
			
			if($DB->record_exists('course', array('id' => $course)))
				$this->course = $course;
			else
			{
				print_error('courseidnotfound','error');
				exit(0);
			}
		}
		
		public function get_filetypes()
		{
			return $this->filetypes;
		}
		
		public function init_format($format)
		{
			if(in_array($format,$this->get_filetypes()))
			{
				$exportformat_class = 'exportformat_'.$format;
				$this->format = new $exportformat_class();
				return true;
			}	
			else return false;
		}
		
		public function create_file($lang)
		{
			global $CFG,$DB;

			if(!isset($this->format))
			{
				print_error('format not initialized','block_coursefeedback');
			}
			else
			{
				$answers = get_answers($this->course);
				$this->reset();
				$this->content = $this->format->build($answers,$lang);
			}
		}
		
		public function get_content()
		{
 			return $this->content;
		}
		
		public function reset()
		{
			$this->content = '';
		}
	}

/**
 * @author Jan Eberhardt
 * Generell format class. Doesn't contain very much so far, but should provide basics.
 */
abstract class exportformat
{
	private $type;
	
	public final function get_type()
	{
		return $this->type;
	}
	
	public abstract function build($arg1);
}

/**
 * @author Jan Eberhardt
 * CSV export class
 */
class exportformat_csv extends exportformat
{
	public  $seperator;
	public  $newline;
	
	public function __construct()
	{
		$this->type      = 'csv';
		$this->seperator = ';';
		$this->newline   = "\n";
	}
	
	public function build($answers,$lang = null)
	{
		global $DB;
		$config  = get_config('block_coursefeedback');
		$content = get_string('download_thead_questions','block_coursefeedback').$this->seperator.get_string('table_html_abstain','block_coursefeedback');
		for($i=1;$i<7;$i++) $content .= $this->seperator.$i;
		$content .= $this->newline;
		
		$lang = find_language($lang);
		
		foreach($answers as $questionid => $values)
		{
			$conditions = array('coursefeedbackid'=>$config->active_feedback,'language'=>$lang,'questionid'=>$questionid);
			if($question = $DB->get_field('block_coursefeedback'.'_questns','question',$conditions));
				$content .= format_text($question,FORMAT_PLAIN).$this->seperator.join($this->seperator,$values).$this->newline;
		}
		return $content;
	}
}
?>