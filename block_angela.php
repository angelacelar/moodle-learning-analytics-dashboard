<?php
class block_angela extends block_base {
	public function init(){
		$this->title = 'Try your HTML';
	}
	
	public function get_content(){
		global $CFG, $PAGE;
		
		if ($this->content !== null){
			return $this->content;
		}
		
		$this->content = new stdClass;
		$this->content->text = '';
		$button = <<<EOB
		<button type="button" id ="button">
			<img src='$CFG->wwwroot/blocks/angela/image/html.png' height=300px width=300px>
		</button>
EOB;
		$this->content->text.=$button;
		
		//modalni prozor
		$PAGE->requires->js_call_amd('block_angela/htmlmodal',"#button");
		
		return $this->content;
	}
	public function applicable_formats(){
		return array(
			'site-index' => false,
			'course-view' => true,
			'mod-quiz' => true
		);
	}
}
?>