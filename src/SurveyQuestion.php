<?php

namespace Jobsys\Survey;

final class SurveyQuestion
{
    const QUESTION_TYPE_RADIO = 1;
    const QUESTION_TYPE_CHECKBOX = 2;
    const QUESTION_TYPE_INPUT = 3;
    const QUESTION_TYPE_MATRIX_TABLE = 4;
    const QUESTION_TYPE_MATRIX_RADIO = 5;
    const QUESTION_TYPE_MATRIX_CHECKBOX = 6;
    const QUESTION_TYPE_ORDER_CHECKBOX = 7;
    const QUESTION_TYPE_DATE = 8;
    const QUESTION_TYPE_AREA = 9;
    const QUESTION_TYPE_NUMBER = 10;

    public function __construct()
    {
    }
}
