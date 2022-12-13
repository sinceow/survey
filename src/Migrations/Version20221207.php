<?php

namespace Jobsys\Survey\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221207 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $table_survey = $schema->createTable('surveys');
        $table_survey->addColumn('id', 'integer', ['autoincrement' => true])->setComment('ID');
        $table_survey->addColumn('title', 'string', ['length' => 191])->setComment('问卷标题');
        $table_survey->addColumn('type', 'smallint', ['notnull' => false])->setComment('问卷类型');
        $table_survey->addColumn('intro', 'text', ['notnull' => false])->setComment('问卷开场语');
        $table_survey->addColumn('outro', 'text', ['notnull' => false])->setComment('问卷结尾语');
        $table_survey->addColumn('started_at', 'integer', ['notnull' => false])->setComment('开始时间');
        $table_survey->addColumn('ended_at', 'integer', ['notnull' => false])->setComment('结束时间');
        $table_survey->addColumn('show_type', 'smallint', ['notnull' => false])->setComment('显示类型: 1 是按题目；2 是全部');
        $table_survey->addColumn('creator', 'string', ['length' => 191, 'notnull' => false])->setComment('创建者');
        $table_survey->addColumn('is_required', 'boolean', ['notnull' => false, 'default' => 0])->setComment('是否必填');
        $table_survey->addColumn('is_refillable', 'boolean', ['notnull' => false, 'default' => 0])->setComment('是否可重复填写');
        $table_survey->addColumn('is_active', 'boolean', ['default' => 1])->setComment('是否激活');
        $table_survey->addColumn('created_at', 'integer', ['notnull' => false])->setComment('创建时间');
        $table_survey->addColumn('updated_at', 'integer', ['notnull' => false])->setComment('更新时间');
        $table_survey->setPrimaryKey(['id']);
        $table_survey->addIndex(['title', 'creator']);

        $table_survey_question = $schema->createTable('survey_questions');
        $table_survey_question->addColumn('id', 'integer', ['autoincrement' => true])->setComment('ID');
        $table_survey_question->addColumn('survey_id', 'integer', ['notnull' => false])->setComment('问卷ID');
        $table_survey_question->addColumn('title', 'string', ['length' => 191])->setComment('题目');
        $table_survey_question->addColumn('type', 'smallint', ['notnull' => false])->setComment('题目类型');
        $table_survey_question->addColumn('is_required', 'boolean', ['default' => 0])->setComment('是否必填');
        $table_survey_question->addColumn('is_end', 'boolean', ['default' => 0])->setComment('是否最后一题');
        $table_survey_question->addColumn('style', 'string', ['length' => 10, 'notnull' => false])->setComment('样式');
        $table_survey_question->addColumn('min', 'integer', ['default' => 0, 'notnull' => false])->setComment('最小值');
        $table_survey_question->addColumn('max', 'integer', ['default' => 0, 'notnull' => false])->setComment('最大值');
        $table_survey_question->addColumn('sort_order', 'string', ['notnull' => false, 'length' => 191])->setComment('排序');
        $table_survey_question->addColumn('chapter', 'string', ['length' => 191, 'notnull' => false])->setComment('章节');
        $table_survey_question->setPrimaryKey(['id']);
        $table_survey_question->addIndex(['survey_id']);

        $table_survey_option = $schema->createTable('survey_options');
        $table_survey_option->addColumn('id', 'integer', ['autoincrement' => true])->setComment('ID');
        $table_survey_option->addColumn('survey_id', 'integer', ['notnull' => false])->setComment('问卷ID');
        $table_survey_option->addColumn('survey_question_id', 'integer', ['notnull' => false])->setComment('题目ID');
        $table_survey_option->addColumn('title', 'string', ['length' => 191])->setComment('选项');
        $table_survey_option->addColumn('sort_order', 'string', ['notnull' => false, 'length' => 191])->setComment('排序');
        $table_survey_option->addColumn('is_fillable', 'boolean', ['default' => 0])->setComment('是否可编辑');
        $table_survey_option->addColumn('jump_to_question_order', 'integer', ['notnull' => false])->setComment('跳转到题目序号');
        $table_survey_option->addColumn('hide_question_ids', 'string', ['length' => 250, 'notnull' => false])->setComment('隐藏题目');
        $table_survey_option->setPrimaryKey(['id']);
        $table_survey_option->addIndex(['survey_id', 'survey_question_id']);

        $table_survey_answer = $schema->createTable('survey_answers');
        $table_survey_answer->addColumn('id', 'integer', ['autoincrement' => true])->setComment('ID');
        $table_survey_answer->addColumn('survey_id', 'integer', ['notnull' => false])->setComment('问卷ID');
        $table_survey_answer->addColumn('survey_question_id', 'integer', ['notnull' => false])->setComment('题目ID');
        $table_survey_answer->addColumn('survey_option_id', 'integer', ['notnull' => false])->setComment('选项ID');
        $table_survey_answer->addColumn('user_id', 'string', ['notnull' => false, 'length' => 191])->setComment('用户ID');
        $table_survey_answer->addColumn('user_type', 'string', ['notnull' => false, 'length' => 191])->setComment('用户类型');
        $table_survey_answer->addColumn('answer', 'text', ['notnull' => false])->setComment('答案');
        $table_survey_answer->addColumn('created_at', 'integer', ['notnull' => false])->setComment('创建时间');
        $table_survey_answer->addColumn('ip', 'string', ['length' => 100, 'notnull' => false])->setComment('IP');
        $table_survey_answer->setPrimaryKey(['id']);
        $table_survey_answer->addIndex(['survey_id', 'survey_question_id', 'survey_option_id', 'user_id', 'user_type']);

        $table_survey_user = $schema->createTable('survey_users');
        $table_survey_user->addColumn('id', 'integer', ['autoincrement' => true])->setComment('ID');
        $table_survey_user->addColumn('survey_id', 'integer', ['notnull' => false])->setComment('问卷ID');
        $table_survey_user->addColumn('user_id', 'string', ['notnull' => false, 'length' => 191])->setComment('用户ID');
        $table_survey_user->addColumn('user_type', 'string', ['notnull' => false, 'length' => 191])->setComment('用户类型');
        $table_survey_user->addColumn('last_question_id', 'integer', ['notnull' => false])->setComment('最后一题ID');
        $table_survey_user->addColumn('status', 'smallint', ['notnull' => false])->setComment('填写状态: 未填写，填写中，已完成');
        $table_survey_user->addColumn('created_at', 'integer', ['notnull' => false])->setComment('创建时间');
        $table_survey_user->addColumn('updated_at', 'integer', ['notnull' => false])->setComment('更新时间');
        $table_survey_user->setPrimaryKey(['id']);
        $table_survey_user->addIndex(['survey_id', 'user_id', 'user_type']);
    }


    public function down(Schema $schema): void
    {
        if ($schema->hasTable('surveys')) {
            $schema->dropTable('surveys');
        }
        if ($schema->hasTable('survey_questions')) {
            $schema->dropTable('survey_questions');
        }
        if ($schema->hasTable('survey_options')) {
            $schema->dropTable('survey_options');
        }
        if ($schema->hasTable('survey_answers')) {
            $schema->dropTable('survey_answers');
        }
        if ($schema->hasTable('survey_users')) {
            $schema->dropTable('survey_users');
        }
    }
}