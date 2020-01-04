<?php
class block_la_dashboard extends block_base{
	public function init(){
		$this->title = get_string('myplugin', 'block_la_dashboard');
		
	}
	
	public function get_content(){
		global $USER;
		global $COURSE;
		
		require_login();
		if ($this->content !== null){
			return $this->content;
		}
		$user_id = $USER->firstname;
		$course = $COURSE->id;
		
		$url_1 = new moodle_url('/blocks/la_dashboard/grades_chart.php', array('blockid' => $this->instance->id, 'courseid' => $course, 'userid' => $user_id));
		
		$this->content = new stdClass;
		$this->content->text = 'Zanima te predviđena ocjena i napredak na kolegiju? Ovaj blok daje upravo te informacije, '.$user_id.'!</br>';
		$this->content->text .= html_writer::link($url_1, get_string('pogledaj_stanje', 'block_la_dashboard'));
				
	}
}
?>