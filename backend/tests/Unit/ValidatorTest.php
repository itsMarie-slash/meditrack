<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit;

use MediTrack\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredFieldMissing(): void
    {
        $errors = (new Validator([], ['username' => 'required']))->validate();
        $this->assertArrayHasKey('username', $errors);
    }

    public function testRequiredFieldPresentPasses(): void
    {
        $errors = (new Validator(['username' => 'bhw.admin'], ['username' => 'required']))->validate();
        $this->assertSame([], $errors);
    }

    /** @dataProvider validPhMobileNumbers */
    public function testValidPhMobileNumbersPass(string $number): void
    {
        $errors = (new Validator(['mobile' => $number], ['mobile' => 'ph_mobile']))->validate();
        $this->assertSame([], $errors);
    }

    public static function validPhMobileNumbers(): array
    {
        return [['09171234567'], ['+639171234567']];
    }

    /** @dataProvider invalidPhMobileNumbers */
    public function testInvalidPhMobileNumbersFail(string $number): void
    {
        $errors = (new Validator(['mobile' => $number], ['mobile' => 'ph_mobile']))->validate();
        $this->assertArrayHasKey('mobile', $errors);
    }

    public static function invalidPhMobileNumbers(): array
    {
        return [['12345'], ['639171234567'], ['0917-123-4567'], ['not-a-number']];
    }

    public function testDateNotFutureRejectsFutureDate(): void
    {
        $future = date('Y-m-d', strtotime('+1 year'));
        $errors = (new Validator(['birthdate' => $future], ['birthdate' => 'date_not_future']))->validate();
        $this->assertArrayHasKey('birthdate', $errors);
    }

    public function testDateNotFutureAcceptsPastDate(): void
    {
        $errors = (new Validator(['birthdate' => '1950-01-01'], ['birthdate' => 'date_not_future']))->validate();
        $this->assertSame([], $errors);
    }

    public function testInRuleRejectsValueOutsideList(): void
    {
        $errors = (new Validator(['status' => 'bogus'], ['status' => 'in:active,pending,inactive']))->validate();
        $this->assertArrayHasKey('status', $errors);
    }

    public function testOptionalFieldSkipsValidationWhenEmpty(): void
    {
        // Non-required fields must not fail validation just because they're blank
        // (e.g. an unassigned medicine dropdown submitting "").
        $errors = (new Validator(['assigned_medicine_id' => ''], ['assigned_medicine_id' => 'integer']))->validate();
        $this->assertSame([], $errors);
    }
}
