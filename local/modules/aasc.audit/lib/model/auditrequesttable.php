<?php
namespace Aasc\Audit\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;

class AuditRequestTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'aasc_audit_request';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new StringField('COMPANY_NAME', [
                'required' => true,
                'validation' => function () {
                    return [new LengthValidator(2, 255)];
                },
            ]),
            new StringField('TAX_CODE', [
                'required' => true,
                'validation' => function () {
                    return [new LengthValidator(10, 20)];
                },
            ]),
            new FloatField('ANNUAL_REVENUE', [
                'default_value' => 0.0,
            ]),
            new StringField('CONTACT_NAME', [
                'required' => true,
                'validation' => function () {
                    return [new LengthValidator(2, 255)];
                },
            ]),
            new StringField('PHONE', [
                'required' => true,
            ]),
            new StringField('EMAIL', [
                'required' => true,
            ]),
            new IntegerField('SERVICE_ID', [
                'default_value' => 0,
            ]),
            new StringField('STATUS', [
                'default_value' => 'NEW',
            ]),
            new StringField('REMINDER_SENT', [
                'default_value' => 'N',
            ]),
            new IntegerField('CRM_LEAD_ID', [
                'default_value' => 0,
            ]),
            new DatetimeField('CREATED_AT', [
                'required' => true,
            ]),
            new DatetimeField('UPDATED_AT', [
                'default_value' => function () {
                    return new \Bitrix\Main\Type\DateTime();
                },
            ]),
        ];
    }
}
