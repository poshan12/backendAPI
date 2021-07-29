<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Syllabus_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();

    }

    public function getDetailbyDateandTime($subject_group_subject_id, $subject_group_class_sections_id, $time_from, $time_to, $date)
    {
        
        $sql   = "SELECT lesson.name as `lesson_name`,topic.id as `topic_name`,topic.name as `topic_name`,subject_syllabus.* FROM `lesson` inner join topic on topic.lesson_id = lesson.id INNER JOIN subject_syllabus on subject_syllabus.topic_id=topic.id WHERE subject_group_subject_id =" . $this->db->escape($subject_group_subject_id) . " and subject_group_class_sections_id=" . $this->db->escape($subject_group_class_sections_id) . " and subject_syllabus.date=" . $this->db->escape($date) . " and subject_syllabus.time_from=" . $this->db->escape($time_from) . " and subject_syllabus.time_to=" . $this->db->escape($time_to);
        $query = $this->db->query($sql);
        return $query->result();
    }

    public function getDetailbyDateandTime1($subject_group_subject_id, $subject_group_class_sections_id, $time_from, $time_to, $date,$date1)
    {
        if(empty($subject_group_class_sections_id) and empty($subject_group_subject_id) and empty($time_from) and empty($time_to) and empty($date) and empty($date1))
        {
            $sql   = "SELECT lesson.name as `lesson_name`,topic.id as `topic_name`,topic.name as `topic_name`,subject_syllabus.* FROM `lesson` inner join topic on topic.lesson_id = lesson.id INNER JOIN subject_syllabus on subject_syllabus.topic_id=topic.id";
            $query = $this->db->query($sql);
            return $query->result();
        }
        else if(!empty($subject_group_class_sections_id) and !empty($subject_group_subject_id) and !empty($time_from) and !empty($time_to) and !empty($date))
        {
            $sql   = "SELECT lesson.name as `lesson_name`,topic.id as `topic_name`,topic.name as `topic_name`,subject_syllabus.* FROM `lesson` inner join topic on topic.lesson_id = lesson.id INNER JOIN subject_syllabus on subject_syllabus.topic_id=topic.id WHERE subject_group_subject_id =" . $this->db->escape($subject_group_subject_id) . " and subject_group_class_sections_id=" . $this->db->escape($subject_group_class_sections_id) . " and subject_syllabus.date=" . $this->db->escape($date) . " and subject_syllabus.time_from=" . $this->db->escape($time_from) . " and subject_syllabus.time_to=" . $this->db->escape($time_to);
            $query = $this->db->query($sql);
            return $query->result();
        }
        else if(!empty($date) and empty($subject_group_class_sections_id) and empty($subject_group_subject_id) and empty($time_from) and empty($time_to))
        {
            $sql   = "SELECT lesson.name as `lesson_name`,topic.id as `topic_name`,topic.name as `topic_name`,subject_syllabus.* FROM `lesson` inner join topic on topic.lesson_id = lesson.id INNER JOIN subject_syllabus on subject_syllabus.topic_id=topic.id WHERE  subject_syllabus.date=" . $this->db->escape($date) . "";
            $query = $this->db->query($sql);
            return $query->result();
        }
        else if(!empty($date) and !empty($time_from) and !empty($time_to) and empty($subject_group_class_sections_id) and empty($subject_group_subject_id)) {
            $sql   = "SELECT lesson.name as `lesson_name`,topic.id as `topic_name`,topic.name as `topic_name`,subject_syllabus.* FROM `lesson` inner join topic on topic.lesson_id = lesson.id INNER JOIN subject_syllabus on subject_syllabus.topic_id=topic.id WHERE subject_syllabus.date=" . $this->db->escape($date) . " and subject_syllabus.time_from=" . $this->db->escape($time_from) . " and subject_syllabus.time_to=" . $this->db->escape($time_to);
            $query = $this->db->query($sql);
            return $query->result();
        }
        else if(!empty($date) and !empty($date1) and empty($time_to) and empty($time_from) and empty($subject_group_class_sections_id) and empty($subject_group_subject_id)) {
            $sql  = "SELECT lesson.name as `lesson_name`,topic.id as `topic_name`,topic.name as `topic_name`,subject_syllabus.* FROM `lesson` inner join topic on topic.lesson_id = lesson.id INNER JOIN subject_syllabus on subject_syllabus.topic_id=topic.id WHERE  subject_syllabus.date BETWEEN " . $this->db->escape($date) . " and " . $this->db->escape($date1) . "";
            // $sql   = "SELECT lesson.name as `lesson_name`,topic.id as `topic_name`,topic.name as `topic_name`,subject_syllabus.* FROM `lesson` inner join topic on topic.lesson_id = lesson.id INNER JOIN subject_syllabus on subject_syllabus.topic_id=topic.id WHERE subject_syllabus.date=" . $this->db->escape($date) . " and subject_syllabus.time_from=" . $this->db->escape($time_from) . " and subject_syllabus.time_to=" . $this->db->escape($time_to);
            $query = $this->db->query($sql);
            return $query->result();
        }
    }

    public function getSyllabusSubjects($class_id, $section_id)
    {
        $sql = "SELECT subject_group_class_sections.*,subject_groups.name,subject_group_subjects.subject_id,subject_group_subjects.id as `subject_group_subject_id`,subjects.name as `subject_name` , subjects.code as `subject_code` ,(select count(*) from lesson INNER JOIN topic on topic.lesson_id=lesson.id WHERE lesson.subject_group_subject_id=subject_group_subjects.id and lesson.subject_group_class_sections_id=subject_group_class_sections.id) as `total`,(select count(*) from lesson INNER JOIN topic on topic.lesson_id=lesson.id WHERE lesson.subject_group_subject_id=subject_group_subjects.id and lesson.subject_group_class_sections_id=subject_group_class_sections.id and topic.status=1) as `total_complete` from class_sections INNER join subject_group_class_sections on subject_group_class_sections.class_section_id=class_sections.id INNER JOIN subject_groups on subject_groups.id = subject_group_class_sections.subject_group_id INNER JOIN subject_group_subjects on subject_group_subjects.subject_group_id=subject_groups.id INNER JOIN subjects on subjects.id = subject_group_subjects.subject_id WHERE class_sections.class_id=" . $this->db->escape($class_id) . " and class_sections.section_id=" . $this->db->escape($section_id);

        $query = $this->db->query($sql);
        return $query->result();
    }

    public function getSubjectsLesson($subject_group_subject_id, $subject_group_class_sections_id)
    {

        $result = array();
        $sql    = "SELECT lesson.*,(select count(*) from topic WHERE topic.lesson_id=lesson.id) as `total`,(select count(*) from topic WHERE topic.lesson_id=lesson.id and topic.status=1) as `total_complete` FROM `lesson` WHERE subject_group_subject_id=" . $this->db->escape($subject_group_subject_id) . " and subject_group_class_sections_id=" . $this->db->escape($subject_group_class_sections_id);

        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            $result = $query->result();
            foreach ($result as $result_key => $result_value) {
                $lesson_id = $result_value->id;
                $this->db->from('topic');
                $this->db->where('topic.lesson_id', $lesson_id);
                $this->db->order_by('topic.id');
                $query = $this->db->get();
                $topics                          = $query->result();
                $result[$result_key]->{"topics"} = $topics;
            }
        }
        return $result;
    }

}
