<?php

namespace Jobsys\Survey\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Jobsys\Survey\Survey;
use Jobsys\Survey\SurveyQuestion;
use Jobsys\Survey\Utils\Db;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

class SurveyService
{

    protected Connection $conn;

    protected ?string $t_survey;
    protected ?string $t_question;
    protected ?string $t_option;
    protected ?string $t_answer;
    protected ?string $t_user;

    public function __construct($db_params = [])
    {
        $this->conn = Db::getConnection($db_params);

        $this->t_survey = Db::getTable('surveys');
        $this->t_question = Db::getTable('survey_questions');
        $this->t_option = Db::getTable('survey_options');
        $this->t_answer = Db::getTable('survey_answers');
        $this->t_user = Db::getTable('survey_users');
    }

    /**
     * 保存问卷
     * @param array $survey
     * @return array
     * @throws Exception
     */
    public function saveSurvey(array $survey): array
    {
        if (isset($survey['id']) && $survey['id']) {
            $survey['updated_at'] = time();
            $this->conn->update($this->t_survey, $survey, ['id' => $survey['id']]);
        } else {
            $survey['created_at'] = time();
            $survey['updated_at'] = time();
            $this->conn->insert($this->t_survey, $survey);
            $survey['id'] = $this->conn->lastInsertId();
        }

        return [$survey, null];
    }

    /**
     * 删除问卷
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function deleteSurvey(int $id): array
    {
        //获取题目ID
        $questions = $this->conn->createQueryBuilder()
            ->select('id')
            ->from($this->t_question)
            ->where('survey_id = :survey_id')
            ->setParameter('survey_id', $id)
            ->fetchAllAssociative();

        $question_ids = array_column($questions, 'id');

        try {
            $this->conn->beginTransaction();

            //删除问卷
            $this->conn->delete($this->t_survey, ['id' => $id]);

            //删除题目
            $this->conn->delete($this->t_question, ['survey_id' => $id]);

            //删除选项
            $this->conn->createQueryBuilder()->delete($this->t_option)->where('survey_question_id IN (:question_ids)')
                ->setParameter('question_ids', $question_ids, Connection::PARAM_INT_ARRAY)->executeStatement();


            //删除答案
            $this->conn->createQueryBuilder()->delete($this->t_answer)->where('survey_question_id IN (:question_ids)')
                ->setParameter('question_ids', $question_ids, Connection::PARAM_INT_ARRAY)->executeStatement();

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [false, $e->getMessage()];
        }
        return [true, null];
    }

    /**
     * 保存题目
     * @param array $question
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function saveQuestion(array $question, array $options = []): array
    {
        try {
            $this->conn->beginTransaction();

            //修改
            if (isset($question['id']) && $question['id']) {
                $question_id = $question['id'];

                //是否存在题目
                $question_exists = $this->conn->createQueryBuilder()
                    ->select('id')
                    ->from($this->t_question)
                    ->where('id <> :id')
                    ->andWhere('survey_id = :survey_id')
                    ->andWhere('sort_order = :sort_order')
                    ->setParameter('id', $question_id)
                    ->setParameter('survey_id', $question['survey_id'])
                    ->setParameter('sort_order', $question['sort_order'])
                    ->executeQuery()
                    ->rowCount();

                if ($question_exists) {
                    throw new Exception('该问卷已存在相同序号的题目');
                }

                $result = $this->conn->update($this->t_question, $question, ['id' => $question_id]);

                if (!$result) {
                    throw new Exception('修改题目失败');
                }


                $exists_option_ids = array_column($options, 'id');


                //删除不在$exists_option_ids中的选项
                $this->conn->createQueryBuilder()
                    ->delete($this->t_option)
                    ->where('survey_question_id = :question_id')
                    ->andWhere('id NOT IN (:ids)')
                    ->setParameter('question_id', $question_id)
                    ->setParameter('ids', $exists_option_ids, Connection::PARAM_INT_ARRAY)
                    ->executeStatement();

                //更新选项
                foreach ($options as $option) {
                    $option['survey_id'] = $question['survey_id'];
                    $option['survey_question_id'] = $question_id;


                    if (isset($option['id']) && $option['id']) {
                        //是否存在该选项
                        $option_exists = $this->conn->createQueryBuilder()
                            ->select('id')
                            ->from($this->t_option)
                            ->where('id <> :id')
                            ->andWhere('title = :title')
                            ->andWhere('survey_question_id = :question_id')
                            ->setParameter('id', $option['id'])
                            ->setParameter('title', $option['title'])
                            ->setParameter('question_id', $question_id)
                            ->executeQuery()
                            ->rowCount();

                        if ($option_exists) {
                            throw new Exception('该题目已存在相同选项: ' . $option['title']);
                        }

                        $this->conn->update($this->t_option, $option, ['id' => $option['id']]);
                    } else {

                        //是否存在该选项
                        $option_exists = $this->conn->createQueryBuilder()
                            ->select('id')
                            ->from($this->t_option)
                            ->where('title = :title')
                            ->andWhere('survey_question_id = :question_id')
                            ->setParameter('title', $option['title'])
                            ->setParameter('question_id', $question_id)
                            ->executeQuery()
                            ->rowCount();

                        if ($option_exists) {
                            throw new Exception('该题目已存在相同选项: ' . $option['title']);
                        }

                        $this->conn->insert($this->t_option, $option);
                    }
                }
            } else {
                $question_exists = $this->conn->createQueryBuilder()
                    ->select('id')
                    ->from($this->t_question)
                    ->andWhere('survey_id = :survey_id')
                    ->andWhere('sort_order = :sort_order')
                    ->setParameter('survey_id', $question['survey_id'])
                    ->setParameter('sort_order', $question['sort_order'], ParameterType::INTEGER)
                    ->executeQuery()
                    ->rowCount();

                if ($question_exists) {
                    throw new Exception('该问卷已存在相同序号的题目');
                }

                //保存题目
                $result = $this->conn->insert($this->t_question, $question);
                $question['id'] = $this->conn->lastInsertId();
                $question_id = $question['id'];
                if (!$result) {
                    throw new Exception('保存题目失败');
                }
            }

            //如果为填空题，清除选项
            if ($question['type'] == SurveyQuestion::QUESTION_TYPE_INPUT || $question['type'] == SurveyQuestion::QUESTION_TYPE_NUMBER) {
                $options = false;
            }

            //插入选项
            if ($options) {
                foreach ($options as $option) {
                    $option['survey_question_id'] = $question_id;
                    $result = $this->conn->insert($this->t_option, $option);
                    if (!$result) {
                        throw new Exception('保存选项失败');
                    }
                }
            }

            //记录修改时间
            $this->conn->update($this->t_survey, ['updated_at' => time()], ['id' => $question['survey_id']]);

            $this->conn->commit();

            return [$question, null];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return [false, $e->getMessage()];
        }
    }

    /**
     * 删除题目
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteQuestions(array $ids = []): array
    {
        try {
            $this->conn->beginTransaction();

            $questions = $this->conn->createQueryBuilder()
                ->select('id, survey_id')
                ->from($this->t_question)
                ->where('id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY)
                ->executeQuery()
                ->fetchAllAssociative();

            if (!$questions) {
                throw new Exception('题目不存在');
            }

            $survey_ids = array_column($questions, 'survey_id');


            //删除题目
            $result = $this->conn->createQueryBuilder()
                ->delete($this->t_question)
                ->where('id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY)
                ->executeStatement();

            if (!$result) {
                throw new Exception('删除题目失败');
            }

            //删除选项
            $this->conn->createQueryBuilder()
                ->delete($this->t_option)
                ->where('survey_question_id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY)
                ->executeStatement();


            //删除答案
            $this->conn->createQueryBuilder()
                ->delete($this->t_answer)
                ->where('survey_question_id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY)
                ->executeStatement();

            //记录修改时间
            $this->conn->createQueryBuilder()
                ->update($this->t_survey)
                ->set('updated_at', time())
                ->where('id IN (:ids)')
                ->setParameter('ids', $survey_ids, Connection::PARAM_INT_ARRAY)
                ->executeStatement();

            $this->conn->commit();
            return [true, null];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [false, $e->getMessage()];
        }
    }

    /**
     * 获取问卷
     * @param $survey_id
     * @param bool $include_questions
     * @param bool $include_options
     * @return array
     * @throws Exception
     */
    public function getSurvey($survey_id, bool $include_questions = false, bool $include_options = false): array
    {
        //检测问卷是否存在
        $survey = $this->conn->createQueryBuilder()
            ->select('*')
            ->from($this->t_survey)
            ->where('id = :id')
            ->setParameter('id', $survey_id)
            ->executeQuery()
            ->fetchAssociative();

        if (!$survey) {
            return [false, '问卷不存在'];
        }

        if ($include_questions) {
            $questions = $this->conn->createQueryBuilder()
                ->select('id, title, type, sort_order')
                ->from($this->t_question)
                ->where('survey_id = :survey_id')
                ->setParameter('survey_id', $survey_id)
                ->orderBy('sort_order', 'ASC')
                ->executeQuery()
                ->fetchAllAssociative();

            if (!$questions) {
                return [[], null];
            }

            if ($include_options) {
                $question_ids = array_column($questions, 'id');

                $options = $this->conn->createQueryBuilder()
                    ->select('id, survey_question_id, title, sort_order')
                    ->from($this->t_option)
                    ->where('survey_question_id IN (:ids)')
                    ->setParameter('ids', $question_ids, Connection::PARAM_INT_ARRAY)
                    ->orderBy('sort_order', 'ASC')
                    ->executeQuery()
                    ->fetchAllAssociative();

                //options 按 survey_question_id 分组
                $options_group = [];
                foreach ($options as $option) {
                    //整卷展示就清除跳题号
                    if ($survey['show_type'] == Survey::SURVEY_SHOW_TYPE_ALL) {
                        $option['jump_to_question_order'] = 0;
                    }

                    $options_group[$option['survey_question_id']][] = $option;
                }

                foreach ($questions as &$question) {
                    $question['options'] = $options_group[$question['id']] ?? [];
                }
            }

            $survey['questions'] = $questions;

        }

        return [$survey, null];
    }

    /**
     * 获取题目详情
     * @param $id
     * @return array
     * @throws Exception
     */
    public function getQuestion($id): array
    {
        $question = $this->conn->createQueryBuilder()
            ->select('id, title, type, sort_order, survey_id')
            ->from($this->t_question)
            ->where('id = :id')
            ->setParameter('id', $id)
            ->executeQuery()
            ->fetchAssociative();

        if (!$question) {
            return [false, '题目不存在'];
        }

        $survey = $this->conn->createQueryBuilder()
            ->select('id, show_type')
            ->from($this->t_survey)
            ->where('id = :id')
            ->setParameter('id', $question['survey_id'])
            ->fetchAllAssociative();

        if (!$survey) {
            return [false, '问卷不存在'];
        }


        $options = $this->conn->createQueryBuilder()
            ->select('id, survey_question_id, title, sort_order')
            ->from($this->t_option)
            ->where('survey_question_id = :id')
            ->setParameter('id', $id)
            ->orderBy('sort_order', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();


        $question['options'] = $options;
        $question['show_type'] = $survey['show_type'];

        return [$question, null];
    }

    /**
     * 更新题目章节
     * @param array $ids
     * @param $chapter
     * @return array
     * @throws Exception
     */
    public function updateQuestionChapter(array $ids, $chapter): array
    {
        try {
            $this->conn->beginTransaction();

            $questions = $this->conn->createQueryBuilder()
                ->select('id, survey_id')
                ->from($this->t_question)
                ->where('id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY)
                ->executeQuery()
                ->fetchAllAssociative();

            if (!$questions) {
                throw new Exception('题目不存在');
            }

            $survey_ids = array_column($questions, 'survey_id');

            //更新题目

            $result = $this->conn->createQueryBuilder()
                ->update($this->t_question)
                ->set('chapter', ':chapter')
                ->where('id IN (:ids)')
                ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY)
                ->setParameter('chapter', $chapter)
                ->executeStatement();

            if (!$result) {
                throw new Exception('更新题目失败');
            }

            //记录修改时间
            $this->conn->createQueryBuilder()
                ->update($this->t_survey)
                ->set('updated_at', time())
                ->where('id IN (:ids)')
                ->setParameter('ids', $survey_ids, Connection::PARAM_INT_ARRAY)
                ->executeStatement();

            $this->conn->commit();
            return [true, null];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [false, $e->getMessage()];
        }
    }

    /**
     * 回答问卷
     * @param $user_id
     * @param $user_type
     * @param $survey_id
     * @return array|null[]
     * @throws Exception
     */
    public function answerSurvey($user_id, $user_type, $survey_id): array
    {
        //检测问卷
        list($survey, $error) = $this->getCheckedSurvey($survey_id);

        if ($error) {
            return [false, $error];
        }

        //检测回答进度
        $progress = $this->conn->createQueryBuilder()
            ->select('id, status, last_question_id')
            ->from($this->t_user)
            ->where('user_id = :user_id')
            ->andWhere('user_type = :user_type')
            ->andWhere('survey_id = :survey_id')
            ->setParameter('user_id', $user_id)
            ->setParameter('user_type', $user_type)
            ->setParameter('survey_id', $survey_id)
            ->executeQuery()
            ->fetchAssociative();

        if ($progress && $progress['status'] == Survey::SURVEY_USER_STATUS_DONE) {
            return [false, '您已完成本问卷'];
        }

        if ($survey['show_type'] == Survey::SURVEY_SHOW_TYPE_SINGLE || $survey['show_type'] == Survey::SURVEY_SHOW_TYPE_CHAPTER) {
            //拿出最新回答的问题
            $last_question_id = $progress['last_question_id'] ?? 0;
            $last_question = $this->conn->createQueryBuilder()
                ->select('id, title, type, sort_order, survey_id')
                ->from($this->t_question)
                ->where('id = :id')
                ->setParameter('id', $last_question_id)
                ->executeQuery()
                ->fetchAssociative();

            return $this->nextQuestion($survey, $last_question, $user_id, $user_type);
        }

        return [$survey, null];

    }

    /**
     * 提交问题答案
     * @param $user_id
     * @param $user_type
     * @param $survey_id
     * @param $answers
     * @return array|null[]
     * @throws Exception
     */
    public function answerQuestion($user_id, $user_type, $survey_id, $answers): array
    {
        //检测问卷
        list($survey, $error) = $this->getCheckedSurvey($survey_id);

        if ($error) {
            return [false, $error];
        }

        //单题，强制只拿第一条
        if ($survey['show_type'] == Survey::SURVEY_SHOW_TYPE_SINGLE) {
            $answers = array_splice($answers, 0, 1);
        }

        try {
            $this->conn->beginTransaction();

            //遍历答案
            foreach ($answers as $answer) {
                $question_id = $answer['survey_question_id'];


                $question = $this->conn->createQueryBuilder()
                    ->select('*')
                    ->from($this->t_question)
                    ->where('id = :id')
                    ->setParameter('id', $question_id)
                    ->executeQuery()
                    ->fetchAssociative();

                if (!$question) {
                    throw new Exception('题目不存在');
                }

                $data = [
                    'user_id' => $user_id,
                    'user_type' => $user_type,
                    'survey_id' => $survey_id,
                    'survey_question_id' => $question_id,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'created_at' => time(),
                ];

                //检测题目类型
                switch ($question['type']) {
                    case SurveyQuestion::QUESTION_TYPE_RADIO:
                        //检测必填
                        if (!$answer['survey_option_id'] && $question['is_required'] == 1) {
                            throw new Exception('题目' . $question['title'] . '是必填项');
                        }
                        $data['survey_option_id'] = $answer['survey_option_id'];
                        $data['answer'] = $answer['answer'];

                        $exists = $this->conn->createQueryBuilder()
                            ->select('id')
                            ->from($this->t_answer)
                            ->where('user_id = :user_id')
                            ->andWhere('user_type = :user_type')
                            ->andWhere('survey_id = :survey_id')
                            ->andWhere('survey_question_id = :survey_question_id')
                            ->setParameter('user_id', $user_id)
                            ->setParameter('user_type', $user_type)
                            ->setParameter('survey_id', $survey_id)
                            ->setParameter('survey_question_id', $question_id)
                            ->executeQuery()
                            ->fetchAssociative();

                        if ($exists) {
                            $result = $this->conn->update($this->t_answer, $data, ['id' => $exists['id']]);
                        } else {
                            $result = $this->conn->insert($this->t_answer, $data);
                        }
                        if (!$result) {
                            throw new Exception('题目' . $question['title'] . '回答失败');
                        }
                        break;
                    case SurveyQuestion::QUESTION_TYPE_INPUT:
                    case SurveyQuestion::QUESTION_TYPE_DATE:
                    case SurveyQuestion::QUESTION_TYPE_AREA:
                    case SurveyQuestion::QUESTION_TYPE_NUMBER:
                        //检测必填
                        if ((!isset($answer['answer']) || !$answer['answer']) && $question['is_required'] == 1) {
                            throw new Exception('题目' . $question['title'] . '是必填项');
                        }

                        //检测长度
                        if ($question['type'] == SurveyQuestion::QUESTION_TYPE_INPUT && ($question['min'] > 0 || $question['max'] > 0)) {
                            $length = mb_strlen($answer['answer']);
                            if ($question['min'] > 0 && $length < $question['min']) {
                                throw new Exception('题目' . $question['title'] . '最少输入' . $question['min'] . '个字');
                            }

                            if ($question['max'] > 0 && $length > $question['max']) {
                                throw new Exception('题目' . $question['title'] . '最多输入' . $question['max'] . '个字');
                            }
                        } else if ($question['type'] == SurveyQuestion::QUESTION_TYPE_NUMBER && ($question['min'] > 0 || $question['max'] > 0)) {
                            if ($question['min'] > 0 && $answer['answer'] < $question['min']) {
                                throw new Exception('题目' . $question['title'] . '最小值为' . $question['min']);
                            }

                            if ($question['max'] > 0 && $answer['answer'] > $question['max']) {
                                throw new Exception('题目' . $question['title'] . '最大值为' . $question['max']);
                            }
                        }

                        $data['answer'] = $answer['answer'];

                        $exists = $this->conn->createQueryBuilder()
                            ->select('id')
                            ->from($this->t_answer)
                            ->where('user_id = :user_id')
                            ->andWhere('user_type = :user_type')
                            ->andWhere('survey_id = :survey_id')
                            ->andWhere('survey_question_id = :survey_question_id')
                            ->setParameter('user_id', $user_id)
                            ->setParameter('user_type', $user_type)
                            ->setParameter('survey_id', $survey_id)
                            ->setParameter('survey_question_id', $question_id)
                            ->executeQuery()
                            ->fetchAssociative();

                        if ($exists) {
                            $result = $this->conn->update($this->t_answer, $data, ['id' => $exists['id']]);
                        } else {
                            $result = $this->conn->insert($this->t_answer, $data);
                        }
                        if (!$result) {
                            throw new Exception('题目' . $question['title'] . '回答失败');
                        }

                        break;
                    case SurveyQuestion::QUESTION_TYPE_CHECKBOX:
                    case SurveyQuestion::QUESTION_TYPE_MATRIX_TABLE:
                    case SurveyQuestion::QUESTION_TYPE_MATRIX_RADIO:
                    case SurveyQuestion::QUESTION_TYPE_MATRIX_CHECKBOX:
                    case SurveyQuestion::QUESTION_TYPE_ORDER_CHECKBOX:
                        //检测必填
                        if ((!isset($answer['options']) || !count($answer['options'])) && $question['is_required'] == 1) {
                            throw new Exception('题目' . $question['title'] . '是必填项');
                        }

                        //如果是矩阵必填，就要求每一个选项都得必填
                        if (in_array($question['type'], [SurveyQuestion::QUESTION_TYPE_MATRIX_TABLE, SurveyQuestion::QUESTION_TYPE_MATRIX_RADIO, SurveyQuestion::QUESTION_TYPE_MATRIX_CHECKBOX])) {
                            $options = $this->conn->createQueryBuilder()
                                ->select('*')
                                ->from($this->t_option)
                                ->where('survey_question_id = :question_id')
                                ->setParameter('question_id', $question_id)
                                ->executeQuery()
                                ->fetchAllAssociative();

                            $options = array_column($options, null, 'id');

                            foreach ($answer['options'] as $option) {
                                if (!isset($options[$option['survey_option_id']])) {
                                    throw new Exception('题目' . $question['title'] . '选项不存在');
                                }

                                if ($options[$option['survey_option_id']]['is_required'] == 1 && (!$option['answer'] || !isset($option['answer']))) {
                                    throw new Exception('题目' . $question['title'] . '选项' . $options[$option['survey_option_id']]['title'] . '是必填项');
                                }
                            }

                        }

                        //先删除原答案
                        $this->conn->delete($this->t_answer, [
                            'user_id' => $user_id,
                            'user_type' => $user_type,
                            'survey_id' => $survey_id,
                            'survey_question_id' => $question_id,
                        ]);

                        //再插入新答案
                        foreach ($answer['options'] as $option) {
                            $data['survey_option_id'] = $option['survey_option_id'];
                            $data['answer'] = $option['answer'];
                            $result = $this->conn->insert($this->t_answer, $data);
                            if (!$result) {
                                throw new Exception('题目' . $question['title'] . '回答失败');
                            }
                        }
                        break;
                    default:
                        throw new Exception('题目类型错误');
                }

            }

            $this->conn->commit();


            //如果是展示形式是单题模式或章节模式，拿下一题
            if ($survey['show_type'] == Survey::SURVEY_SHOW_TYPE_SINGLE || $survey['show_type'] == Survey::SURVEY_SHOW_TYPE_CHAPTER) {
                if ($survey['show_type'] == Survey::SURVEY_SHOW_TYPE_SINGLE) {
                    $last_question_id = $answers[0]['survey_question_id'];
                } else {
                    $last_question_id = $answers[count($answers) - 1]['survey_question_id'];
                }
                $this->syncUserProgress($survey_id, $last_question_id, $user_id, $user_type, Survey::SURVEY_USER_STATUS_DOING);

                $last_question = $this->conn->createQueryBuilder()
                    ->select('id, sort_order, is_end')
                    ->from($this->t_question)
                    ->where('id = :id')
                    ->setParameter('id', $last_question_id)
                    ->executeQuery()
                    ->fetchAssociative();

                return $this->nextQuestion($survey, $last_question, $user_id, $user_type);

            } else {
                //如果是展示形式是全部模式，直接记录完成结果并返回
                $last_question_id = $answers[count($answers) - 1]['survey_question_id'];
                $this->syncUserProgress($survey_id, $last_question_id, $user_id, $user_type, Survey::SURVEY_USER_STATUS_DONE);
                return [null, null];
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [false, $e->getMessage()];
        }
    }

    /**
     * 导出 Word 格式问卷
     * @param $survey_id
     * @param $path
     * @return array
     * @throws Exception
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    public function exportSurveyAsWord($survey_id, $path): array
    {
        list($survey, $error) = $this->getSurvey($survey_id, true, true);

        if ($error) {
            return [null, $error];
        }

        Settings::setOutputEscapingEnabled(true);
        $phpWord = new PhpWord();

        $phpWord->addFontStyle(
            'sectionName',
            array('name' => '宋体', 'size' => 18, 'bold' => true)
        );

        $section = $phpWord->addSection();

        $phpWord->addFontStyle('rStyle', array('bold' => true, 'size' => 16));
        $phpWord->addParagraphStyle('pStyle', array('align' => 'center', 'spaceAfter' => 100));

        $section->addText($survey['title'], 'rStyle', 'pStyle');
        $section->addTextBreak(2);

        $section->addText('开始时间：' . date('Y-m-d H:i', $survey['started_at']) . '　　结束时间：' . date('Y-m-d H:i', $survey['ended_at']), array('bold' => false, 'size' => 10), array('align' => 'center', 'spaceAfter' => 100));
        $section->addTextBreak(2);

        $section->addText(strip_tags($survey['intro']));
        $section->addTextBreak(2);


        foreach ($survey['questions'] as $k => $v) {
            $survey['questions'][$k]['type_display'] = match ($v['type']) {
                SurveyQuestion::QUESTION_TYPE_RADIO => '单选题',
                SurveyQuestion::QUESTION_TYPE_CHECKBOX => '多选题',
                SurveyQuestion::QUESTION_TYPE_INPUT => '填空题',
                SurveyQuestion::QUESTION_TYPE_MATRIX_TABLE => '矩阵量表题',
                SurveyQuestion::QUESTION_TYPE_MATRIX_RADIO => '矩阵单选题',
                SurveyQuestion::QUESTION_TYPE_MATRIX_CHECKBOX => '矩阵多选题',
                SurveyQuestion::QUESTION_TYPE_ORDER_CHECKBOX => '多选排序题',
                SurveyQuestion::QUESTION_TYPE_DATE => '日期题',
                SurveyQuestion::QUESTION_TYPE_AREA => '地区题',
                SurveyQuestion::QUESTION_TYPE_NUMBER => '数字题',
                default => '未知题型',
            };
            $section->addText($v['sort_order'] . '、' . $v['title'] . '（' . $survey['questions'][$k]['type_display'] . '）');
            //输入选择题选项
            foreach ($v['options'] as $k1 => $v1) {
                $v1_title = $v1['title'];
                if (in_array($v['type'], [SurveyQuestion::QUESTION_TYPE_MATRIX_TABLE, SurveyQuestion::QUESTION_TYPE_MATRIX_RADIO, SurveyQuestion::QUESTION_TYPE_MATRIX_CHECKBOX])) {
                    $v1_titles = json_decode($v1_title, true);
                    $v1_title = '题目[' . $v1_titles['b'] . ']';
                    if ($v1_titles['o']) {
                        $v1_title .= ' 选项[' . join(',', $v1_titles['o']) . ']';
                    }
                    if ($v1_titles['a']) {
                        $v1_title .= ' 提示[' . $v1_titles['a'] . ']';
                    }
                }
                $section->addText('　　' . $v1['sort_order'] . '、' . $v1_title);
            }
            $section->addTextBreak(1);
        }

        $file_name = str_replace(' ', '_', $survey['title'] . date(' Y m d H i s')) . '.docx';

        $file_path = $path . DIRECTORY_SEPARATOR . $file_name;
        $obj_writer = IOFactory::createWriter($phpWord);
        $obj_writer->save($file_path);

        return [$file_name, null];
    }

    /**
     * 获取问卷统计数据
     * @param $survey_id
     * @return array
     * @throws Exception
     */
    public function getSurveyStatistics($survey_id): array
    {
        list($survey, $error) = $this->getSurvey($survey_id, true, true);
        if ($error) {
            return [null, $error];
        }

        //检索出所有的答案并关联查找出题目的类型
        $answers = $this->conn->createQueryBuilder()
            ->select('a.*, q.type')
            ->from($this->t_answer, 'a')
            ->leftJoin('a', $this->t_question, 'q', 'a.survey_question_id = q.id')
            ->where('a.survey_id = :survey_id')
            ->setParameter('survey_id', $survey_id)
            ->orderBy('a.survey_question_id', 'ASC')
            ->fetchAllAssociative();


        $user_statistics = [];
        $answer_statistics = [];

        foreach ($answers as $answer) {
            $question_key = $answer['survey_question_id'];
            $user_value = $answer['user_type'] . '_' . $answer['user_id'];

            //统计答题用户情况
            if (!isset($user_statistics[$question_key])) {
                $user_statistics[$question_key] = [];
            }
            //统计答题答案情况
            if (!isset($answer_statistics[$question_key])) {
                $answer_statistics[$question_key] = [];
            }

            $user_statistics[$question_key][] = $user_value;

            if ($answer['survey_option_id']) {
                $option_key = $answer['survey_option_id'];

                if (in_array($answer['type'], [SurveyQuestion::QUESTION_TYPE_MATRIX_TABLE, SurveyQuestion::QUESTION_TYPE_MATRIX_RADIO, SurveyQuestion::QUESTION_TYPE_MATRIX_CHECKBOX])) {
                    $an = preg_replace(["/^.*【/", "/】/"], "", $answer['answer']);
                    if ($an) {
                        $option_key = $option_key . '__' . $an;
                    }
                }

                if (!isset($answer_statistics[$question_key][$option_key])) {
                    $answer_statistics[$question_key][$option_key] = 0;
                }
                $answer_statistics[$question_key][$option_key]++;
            }
        }

        foreach ($survey['questions'] as &$question) {
            $this->calcQuestionStatistics($answer_statistics, $user_statistics, $question);
        }

        return [$survey, null];
    }

    /**
     * 导出问卷统计数据
     * @param $survey_id
     * @return array
     */
    public function exportSurveyStatistics($survey_id, $path): array
    {

        list($survey, $error) = $this->getSurveyStatistics($survey_id);

        if ($error) {
            return [null, $error];
        }

        $file_name = str_replace(' ', '_', $survey['title'] . ' 统计数据 ' . date(' Y m d H i s')) . '.csv';
        $file_path = $path . DIRECTORY_SEPARATOR . $file_name;


        $headers = ['题号', '题目/选项', '人数', '比例', '答案内容'];

        $file = fopen($file_path, "w+");
        fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($file, $headers);

        $items = [];
        foreach ($survey['questions'] as $question) {
            $items[] = [
                'A' => $question['sort_order'],
                'B' => $question['title'],
                'C' => $question['total'],
                'D' => '',
                'E' => '',
            ];

            if (isset($question['options']) && $question['options']) {
                if (in_array($question['type'], [SurveyQuestion::QUESTION_TYPE_RADIO, SurveyQuestion::QUESTION_TYPE_CHECKBOX, SurveyQuestion::QUESTION_TYPE_ORDER_CHECKBOX])) {
                    foreach ($question['options'] as $option) {
                        $items[] = [
                            'A' => '',
                            'B' => $option['sort_order'],
                            'C' => $option['total'],
                            'D' => $option['rate'] . '%',
                            'E' => $option['title']
                        ];
                    }
                } elseif (in_array($question['type'], [SurveyQuestion::QUESTION_TYPE_MATRIX_TABLE, SurveyQuestion::QUESTION_TYPE_MATRIX_RADIO, SurveyQuestion::QUESTION_TYPE_MATRIX_CHECKBOX])) {
                    foreach ($question['options'] as $option) {
                        $opt_title = json_decode($option['title']);
                        $li = $opt_title->o;
                        foreach ($li as $o) {
                            $items[] = [
                                'A' => '',
                                'B' => $o,
                                'C' => (isset($option['o']) && isset($option['o'][$o]) && isset($option['o'][$o]['total'])) ? $option['o'][$o]['total'] : '',
                                'D' => (isset($option['o']) && isset($option['o'][$o]) && isset($option['o'][$o]['total'])) ? ($option['o'][$o]['rate'] . '%') : '',
                                'E' => $opt_title->b . (isset($opt_title->a) ? (' ' . $opt_title->a) : '')
                            ];
                        }

                    }
                }
            }
        }

        foreach ($items as $line) {
            fputcsv($file, $line);
        }

        fclose($file);

        return [$file_name, null];
    }

    /**
     * 获取问卷统计数据详情【每位用户的每一条记录】
     * @param $survey_id
     * @return array
     */
    public function getSurveyStatisticsDetail($survey_id, $user_type = false): array
    {
        list($survey, $error) = $this->getSurvey($survey_id, true, true);
        if ($error) {
            return [null, $error];
        }

        $builder = $this->conn->createQueryBuilder()->select('distinct(user_id), user_type, survey_question_id, survey_option_id, answer, created_at')
            ->from($this->t_answer)
            ->where('survey_id = :survey_id')
            ->setParameter('survey_id', $survey_id);

        if ($user_type) {
            $builder->andWhere('user_type = :user_type')
                ->setParameter('user_type', $user_type);
        }

        $answers = $builder->fetchAllAssociative();

        $question_map = []; //记录题目的标题
        $option_map = []; //记录选项的标题
        $headers = ['user_id' => '用户ID', 'user_type' => '用户类型']; //表头
        $items = []; //数据

        foreach ($survey['questions'] as $index => $question) {
            $question_key = 'Q_' . $question['id'];
            $question_map[$question_key] = "Q{$question['sort_order']}: {$question['title']}";
            $headers[$question_key] = $question_map[$question_key];

            if (isset($question['options']) && $question['options']) {
                foreach ($question['options'] as $option) {
                    $option_map[$option['id']] = "{$option['sort_order']}: {$option['title']}";
                }
            }
        }


        $user_index_map = []; //记录用户的索引
        foreach ($answers as $answer) {
            $user_key = $answer['user_type'] . '_' . $answer['user_id'];

            if (!isset($user_index_map[$user_key])) {
                $user_index_map[$user_key] = count($user_index_map);
            }

            $item_index = $user_index_map[$user_key];

            $item = ['user_id' => $answer['user_id'], 'user_type' => $answer['user_type']];
            $question_key = 'Q_' . $answer['survey_question_id'];

            if (!isset($item[$question_key])) {
                $item[$question_key] = $option_map[$answer['survey_option_id']];
            } else {
                $item[$question_key] .= '|';
            }

            $items[$item_index] = $item;
        }

        return [compact('headers', 'items'), null];
    }

    /**
     * 获取问卷详情【带基础检验】
     * @param $survey_id
     * @return array
     * @throws Exception
     */
    private function getCheckedSurvey($survey_id): array
    {
        $survey = $this->conn->createQueryBuilder()
            ->select('*')
            ->from($this->t_survey)
            ->where('id = :id')
            ->setParameter('id', $survey_id)
            ->executeQuery()
            ->fetchAssociative();

        if (!$survey) {
            return [false, '问卷不存在'];
        }

        if ($survey['is_active'] != 1) {
            return [false, '该问卷已失效'];
        }

        if ($survey['started_at'] > time()) {
            return [false, '本调查问卷尚没开始，开始时间为：' . date('Y-m-d H:i', $survey['started_at'])];
        }

        if ($survey['ended_at'] < time()) {
            return [false, '本调查问卷已于' . date('Y-m-d H:i', $survey['ended_at']) . '结束'];
        }

        $questions = $this->conn->createQueryBuilder()
            ->select('id, title, type, sort_order')
            ->from($this->t_question)
            ->where('survey_id = :survey_id')
            ->setParameter('survey_id', $survey_id)
            ->orderBy('sort_order', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        if (!$questions) {
            return [[], null];
        }

        $question_ids = array_column($questions, 'id');

        $options = $this->conn->createQueryBuilder()
            ->select('id, survey_question_id, title, sort_order')
            ->from($this->t_option)
            ->where('survey_question_id IN (:ids)')
            ->setParameter('ids', $question_ids, Connection::PARAM_INT_ARRAY)
            ->orderBy('sort_order', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        //options 按 survey_question_id 分组
        $options_group = [];
        foreach ($options as $option) {
            //整卷展示就清除跳题号
            if ($survey['show_type'] == Survey::SURVEY_SHOW_TYPE_ALL) {
                $option['jump_to_question_order'] = 0;
            }

            $options_group[$option['survey_question_id']][] = $option;
        }

        foreach ($questions as &$question) {
            $question['options'] = $options_group[$question['id']] ?? [];
        }

        $survey['questions'] = $questions;

        return [$survey, null];
    }

    /**
     * 同步答题进度
     * @param $survey_id
     * @param $question_id
     * @param $user_id
     * @param $user_type
     * @param $status
     * @return void
     * @throws Exception
     */
    private function syncUserProgress($survey_id, $question_id, $user_id, $user_type, $status): void
    {

        //检测记录是否存在
        $progress = $this->conn->createQueryBuilder()
            ->select('*')
            ->from($this->t_user)
            ->where('survey_id = :survey_id')
            ->andWhere('user_id = :user_id')
            ->andWhere('user_type = :user_type')
            ->setParameter('survey_id', $survey_id)
            ->setParameter('user_id', $user_id)
            ->setParameter('user_type', $user_type)
            ->executeQuery()
            ->fetchAssociative();

        if ($progress) {
            $this->conn->update($this->t_user, [
                'last_question_id' => $question_id,
                'status' => $status,
                'updated_at' => time(),
            ], [
                'id' => $progress['id']
            ]);
        } else {

            $this->conn->insert($this->t_user, [
                'survey_id' => $survey_id,
                'user_id' => $user_id,
                'user_type' => $user_type,
                'last_question_id' => $question_id,
                'status' => $status,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }
    }

    /**
     * 返回的是整个 survey，根据 show_type 的不同， survey['questions'] 中会有不同的题目数据
     * @param $survey
     * @param $last_question
     * @param $user_id
     * @param $user_type
     * @return array|null[]
     * @throws Exception
     */
    private function nextQuestion($survey, $last_question, $user_id, $user_type): array
    {

        $survey_questions = $survey['questions'];

        //如果是最后一题，直接返回
        if (!empty($last_question) && $last_question['sort_order'] > 0 && $last_question['is_end'] == 1) {
            $this->syncUserProgress($survey['id'], $last_question['id'], $user_id, $user_type, Survey::SURVEY_USER_STATUS_DONE);
            return [null, null];
        }

        //如果不是最后一题，则拿下一题
        if (!empty($last_question) && $last_question['sort_order'] > 0) {
            //如果已经完成部分题目，则继续做

            //先联表检测回答的选项是否有跳题
            $jump_question = $this->conn->createQueryBuilder()
                ->select('o.jump_to_question_order')
                ->from($this->t_answer, 'a')
                ->leftJoin('a', $this->t_option, 'o', 'a.survey_option_id = o.id')
                ->where('a.survey_id = :survey_id')
                ->andWhere('a.survey_question_id = :question_id')
                ->setParameter('survey_id', $survey['id'])
                ->setParameter('question_id', $last_question['id'])
                ->executeQuery()
                ->fetchAssociative();

            $next_question_order = $jump_question['jump_to_question_order'] ?? 0;

            if ($next_question_order <= $last_question['sort_order']) {
                $next_question_order = $last_question['sort_order'] + 1;
            }

            //去 survey 的 questions 里找到 sort order 一致的题目
            $next_question = null;
            foreach ($survey_questions as $question) {
                if ($question['sort_order'] >= $next_question_order) {
                    $next_question = $question;
                    break;
                }
            }

            //如果没有找到，则说明已经完成
            if (empty($next_question)) {
                $this->syncUserProgress($survey['id'], $last_question['id'], $user_id, $user_type, Survey::SURVEY_USER_STATUS_DONE);
                return [null, null];
            }

            $survey['questions'] = [$next_question];
        } else {
            //如果没开始做， 先检测 survey 里是否有题， 有题则直接返回第一题
            if (!empty($survey_questions)) {
                $survey['questions'] = [$survey_questions[0]];
            } else {
                return [null, '问卷未设置题目'];
            }
        }

        //如果按章节展示， 则返回下一题的当前章节的所有题目
        if ($survey['show_type'] == Survey::SURVEY_SHOW_TYPE_CHAPTER) {
            $next_question = $survey['questions'][0];
            $chapter_questions = [];
            foreach ($survey_questions as $question) {
                if ($question['chapter'] == $next_question['chapter']) {
                    $chapter_questions[] = $question;
                }
            }
            $survey['questions'] = $chapter_questions;
        }

        return [$survey, null];
    }

    /**
     * 计算每道题的统计
     * @param $answer_statistics
     * @param $user_statistics
     * @param $question
     * @return void
     */
    private function calcQuestionStatistics($answer_statistics, $user_statistics, &$question): void
    {
        $question_key = $question['id'];

        $question['total'] = isset($user_statistics[$question_key]) ? count(array_unique($user_statistics[$question_key])) : 0;

        switch ($question['type']) {
            case SurveyQuestion::QUESTION_TYPE_RADIO:
            case SurveyQuestion::QUESTION_TYPE_CHECKBOX:
            case SurveyQuestion::QUESTION_TYPE_ORDER_CHECKBOX:
                if (isset($answer_statistics[$question_key])) {
                    foreach ($question['options'] as &$option) {
                        $count = intval($answer_statistics[$question_key][$option['id']] ?? 0);
                        $option['total'] = $count;
                        $option['rate'] = $question['total'] > 0 ? round(100 * $count / $question['total'], 2) : 0;
                    }
                }
                break;
            case SurveyQuestion::QUESTION_TYPE_MATRIX_TABLE:
            case SurveyQuestion::QUESTION_TYPE_MATRIX_RADIO:
            case SurveyQuestion::QUESTION_TYPE_MATRIX_CHECKBOX:
                if (isset($answer_statistics[$question_key]) && count($question['options'])) {
                    $o = json_decode($question['options'][0]['title']);
                    $o = $o->o;
                    foreach ($question['options'] as &$option) {
                        $option['o'] = [];
                        foreach ($o as $ot) {
                            $key = $option['id'] . '__' . $ot;
                            $cou = intval($answer_statistics[$question_key][$key] ?? 0);
                            $option['o'][$ot] = [
                                'total' => $cou,
                                'rate' => $question['total'] > 0 ? round(100 * $cou / $question['total'], 2) : 0
                            ];
                        }
                    }
                }
                break;
            default:
                break;
        }

    }

}
