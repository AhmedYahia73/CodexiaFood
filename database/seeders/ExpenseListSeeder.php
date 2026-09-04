<?php

namespace Database\Seeders;

use App\Models\ExpenseList;
use Illuminate\Database\Seeder;

class ExpenseListSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => ['ar' => 'فواتير الكهرباء والمياه', 'en' => 'Electricity & Water Utilities'],
                'description' => ['ar' => 'مصروفات استهلاك الطاقة والمياه للفرع', 'en' => 'Branch utility consumption expenses'],
            ],
            [
                'name' => ['ar' => 'إيجارات الفروع', 'en' => 'Branch Rents'],
                'description' => ['ar' => 'قيم الإيجارات الشهرية للمقرات', 'en' => 'Monthly rental payments for properties'],
            ],
            [
                'name' => ['ar' => 'أجور ومرتبات العاملين', 'en' => 'Staff Salaries & Wages'],
                'description' => ['ar' => 'مستحقات الموظفين والعمال الشهربة', 'en' => 'Monthly staff payroll and bonuses'],
            ],
            [
                'name' => ['ar' => 'صيانة المعدات والأجهزة', 'en' => 'Equipment Maintenance'],
                'description' => ['ar' => 'إصلاح وصيانة أجهزة المطبخ والتكييف', 'en' => 'Kitchen appliances and HVAC repairs'],
            ],
            [
                'name' => ['ar' => 'مستلزمات النظافة والتغليف', 'en' => 'Cleaning & Packaging Supplies'],
                'description' => ['ar' => 'أكياس وعلب التغليف وأدوات التنظيف', 'en' => 'Packing boxes, bags, and sanitization tools'],
            ],
            [
                'name' => ['ar' => 'دعاية وإعلان وتطبيق', 'en' => 'Marketing & Advertising'],
                'description' => ['ar' => 'إعلانات السوشيال ميديا والحملات الترويجية', 'en' => 'Social media ads and promotional campaigns'],
            ],
        ];

        foreach ($categories as $cat) {
            ExpenseList::create([
                'name' => $cat['name'],
                'description' => $cat['description'],
            ]);
        }
    }
}
