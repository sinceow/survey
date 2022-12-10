<?php

namespace Jobsys\Survey;

class Survey
{

    const SURVEY_SHOW_TYPE_SINGLE = 1; //单题展示
    const SURVEY_SHOW_TYPE_ALL = 2; //整卷
    const SURVEY_SHOW_TYPE_CHAPTER = 3; //章节


    const SURVEY_USER_STATUS_UNSTART = 0; //未开始
    const SURVEY_USER_STATUS_DOING = 1; //进行中
    const SURVEY_USER_STATUS_DONE = 2; //已完成


    public function __construct()
    {

    }

}
