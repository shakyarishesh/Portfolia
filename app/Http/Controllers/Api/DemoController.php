<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sandboxed demo endpoints for the API playground.
 * Everything here is invented sample data computed in memory — no database, no employer data.
 */
class DemoController extends Controller
{
    private const EMPLOYEES = [
        ['id' => 1, 'name' => 'Asha Gurung', 'department' => 'engineering', 'designation' => 'Backend Engineer', 'shift' => 'day', 'basic_salary' => 85000],
        ['id' => 2, 'name' => 'Bikash Rai', 'department' => 'engineering', 'designation' => 'QA Engineer', 'shift' => 'day', 'basic_salary' => 60000],
        ['id' => 3, 'name' => 'Chandra Thapa', 'department' => 'finance', 'designation' => 'Accountant', 'shift' => 'day', 'basic_salary' => 55000],
        ['id' => 4, 'name' => 'Deepa Maharjan', 'department' => 'support', 'designation' => 'Support Lead', 'shift' => 'night', 'basic_salary' => 48000],
        ['id' => 5, 'name' => 'Eshan Karki', 'department' => 'support', 'designation' => 'Support Agent', 'shift' => 'night', 'basic_salary' => 38000],
    ];

    /** Illustrative progressive slabs (annual). Not any country's real tax law. */
    private const TAX_SLABS = [[500000, 0.01], [200000, 0.10], [300000, 0.20], [1000000, 0.30], [PHP_INT_MAX, 0.36]];

    public function employees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department' => ['nullable', 'in:engineering,finance,support'],
            'shift' => ['nullable', 'in:day,night'],
        ]);
        $rows = collect(self::EMPLOYEES)
            ->when($data['department'] ?? null, fn ($c, $d) => $c->where('department', $d))
            ->when($data['shift'] ?? null, fn ($c, $s) => $c->where('shift', $s))
            ->values();

        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count(), 'sample_data' => true]]);
    }

    public function employee(int $id): JsonResponse
    {
        $employee = collect(self::EMPLOYEES)->firstWhere('id', $id);

        return $employee
            ? response()->json(['data' => $employee])
            : response()->json(['message' => "Employee {$id} not found."], 404);
    }

    /** Monthly payslip: basic + allowances + overtime − unpaid leave − provident fund − progressive tax. */
    public function payroll(Request $request): JsonResponse
    {
        $in = $request->validate([
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'allowances' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'working_days' => ['required', 'integer', 'between:1,31'],
            'unpaid_leave_days' => ['nullable', 'numeric', 'min:0', 'lte:working_days'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'overtime_multiplier' => ['nullable', 'numeric', 'between:1,3'],
            'provident_fund_percent' => ['nullable', 'numeric', 'between:0,20'],
        ]);

        $basic = (float) $in['basic_salary'];
        $hourly = $basic / $in['working_days'] / 8;
        $overtime = round(($in['overtime_hours'] ?? 0) * $hourly * ($in['overtime_multiplier'] ?? 1.5), 2);
        $leave = round($basic / $in['working_days'] * ($in['unpaid_leave_days'] ?? 0), 2);
        $gross = round($basic + ($in['allowances'] ?? 0) + $overtime - $leave, 2);
        $pf = round(($basic - $leave) * ($in['provident_fund_percent'] ?? 10) / 100, 2);

        // Annualise taxable income, apply slabs, bring back to one month.
        $annualTaxable = max(0, ($gross - $pf) * 12);
        [$annualTax, $breakdown, $remaining] = [0.0, [], $annualTaxable];
        foreach (self::TAX_SLABS as [$width, $rate]) {
            if ($remaining <= 0) {
                break;
            }
            $portion = min($remaining, $width);
            $annualTax += $portion * $rate;
            $breakdown[] = ['taxable' => round($portion, 2), 'rate' => $rate, 'tax' => round($portion * $rate, 2)];
            $remaining -= $portion;
        }
        $tax = round($annualTax / 12, 2);

        return response()->json([
            'data' => [
                'earnings' => ['basic' => $basic, 'allowances' => (float) ($in['allowances'] ?? 0), 'overtime' => $overtime, 'unpaid_leave_deduction' => -$leave, 'gross' => $gross],
                'deductions' => ['provident_fund' => $pf, 'income_tax' => $tax],
                'net_pay' => round($gross - $pf - $tax, 2),
                'tax_breakdown_annual' => $breakdown,
            ],
            'meta' => ['note' => 'Illustrative slabs and rules for demonstration only.', 'sample_data' => true],
        ]);
    }
}
