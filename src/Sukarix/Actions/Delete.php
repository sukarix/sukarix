<?php

declare(strict_types=1);

namespace Sukarix\Actions;

use Sukarix\Enum\ResponseCode;
use Sukarix\Models\Model;

/**
 * Class Delete.
 */
abstract class Delete extends WebAction
{
    protected $recordId;

    /**
     * @var \ReflectionClass
     */
    protected $modelClass;

    /**
     * @var string
     */
    protected $model;

    /**
     * @var Model
     */
    protected $modelInstance;

    /**
     * @var string
     */
    protected $deleteMethodName = 'erase';

    /**
     * @var string
     */
    protected $messageArg;

    /**
     * @param \Base $f3
     * @param array $params
     *
     * @throws
     */
    public function execute($f3, $params): void
    {
        $this->recordId = $params['id'];

        if (null === $this->model) {
            $this->model = $f3->camelcase(mb_convert_case(str_replace('-', '_', mb_strstr($f3->get('ALIAS'), '_delete', true)), MB_CASE_TITLE, 'UTF-8'));
        }

        $this->modelClass    = new \ReflectionClass("Models\\{$this->model}");
        $this->modelInstance = $this->modelClass->newInstance();

        $this->modelInstance->load($this->getFilter());
        $this->logger->info('Built delete action for entity', ['model' => $this->model, 'id' => $this->recordId]);
        if ($this->modelInstance->valid()) {
            $deleteResult = \call_user_func_array([$this->modelInstance, $this->deleteMethodName], []);
            if (false === $deleteResult) {
                $resultCode = ResponseCode::HTTP_INTERNAL_SERVER_ERROR;
                $this->logger->critical('Error occurred while deleting entity', ['model' => $this->model, 'id' => $this->recordId]);
            } else {
                $resultCode = ResponseCode::HTTP_OK;

                if (null !== $this->messageArg) {
                    $message  = $this->i18n->msg(mb_strtolower($this->model) . '.delete_success');
                    $argument = str_starts_with($message, '{0}') ? mb_convert_case($this->modelInstance[$this->messageArg], MB_CASE_TITLE, 'UTF-8') : $this->modelInstance[$this->messageArg];
                }
            }
        } else {
            $resultCode = ResponseCode::HTTP_NOT_FOUND;
            $this->logger->error('Entity could not be deleted', ['model' => $this->model, 'id' => $this->recordId]);
        }

        $this->renderJson([], $resultCode);
    }

    protected function getFilter(): array
    {
        return ['id = ?', $this->recordId];
    }
}
