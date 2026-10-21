<?php

namespace App\Support\Deployment;

use App\Domain\Identity\Enums\Role;
use App\Support\Modules\Module;

/**
 * The kind of institution a client is. Chosen at client creation; it sets
 * default modules, roles and terminology. Differences between clients are
 * data, never `if ($client === ...)` code.
 */
enum InstitutionType: string
{
    case School = 'school';
    case College = 'college';
    case Coaching = 'coaching';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::College => 'College',
            self::Coaching => 'Coaching centre',
        };
    }

    /**
     * Modules enabled unless the client's .env overrides them.
     *
     * @return list<Module>
     */
    public function defaultModules(): array
    {
        $core = [Module::Academic, Module::Admission, Module::Student, Module::Staff, Module::Attendance, Module::Fee, Module::Communication, Module::Report];

        return match ($this) {
            self::School => [...$core, Module::Examination, Module::Transport],
            self::College => [...$core, Module::Examination, Module::Library, Module::Hostel],
            self::Coaching => $core,
        };
    }

    /**
     * Roles seeded for this kind of institution, in addition to Super Admin.
     *
     * @return list<Role>
     */
    public function roles(): array
    {
        return match ($this) {
            self::School => [Role::Admin, Role::Principal, Role::Teacher, Role::Accountant, Role::Guardian, Role::Student],
            self::College => [Role::Admin, Role::Principal, Role::Teacher, Role::Accountant, Role::Student],
            self::Coaching => [Role::Admin, Role::Teacher, Role::Accountant, Role::Guardian, Role::Student],
        };
    }

    /**
     * Words the UI uses for the academic structure.
     *
     * @return array{class: string, classes: string, section: string, sections: string, teacher: string, head: string}
     */
    public function terminology(): array
    {
        return match ($this) {
            self::School => ['class' => 'Class', 'classes' => 'Classes', 'section' => 'Section', 'sections' => 'Sections', 'teacher' => 'Teacher', 'head' => 'Principal'],
            self::College => ['class' => 'Semester', 'classes' => 'Semesters', 'section' => 'Division', 'sections' => 'Divisions', 'teacher' => 'Faculty', 'head' => 'Principal'],
            self::Coaching => ['class' => 'Course', 'classes' => 'Courses', 'section' => 'Batch', 'sections' => 'Batches', 'teacher' => 'Tutor', 'head' => 'Centre head'],
        };
    }
}
