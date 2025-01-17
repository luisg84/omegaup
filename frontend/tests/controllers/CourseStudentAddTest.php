<?php

/**
 *
 * @author @joemmanuel
 */

class CourseStudentAddTest extends OmegaupTestCase {
    /**
     * Basic apiAddStudent test
     */
    public function testAddStudentToCourse() {
        $courseData = CoursesFactory::createCourse();
        $student = UserFactory::createUser();
        $identity = \OmegaUp\DAO\Identities::getByPK($student->main_identity_id);

        $adminLogin = OmegaupTestCase::login($courseData['admin']);
        $response = \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
            ]));

        $this->assertEquals('ok', $response['status']);

        // Validate student was added
        $course = \OmegaUp\DAO\Courses::getByAlias($courseData['course_alias']);
        $this->assertNotNull($course);

        $studentsInGroup = \OmegaUp\DAO\GroupsIdentities::getByPK(
            $course->group_id,
            $identity->identity_id
        );

        $this->assertNotNull($studentsInGroup);
    }

    /**
     * apiAddStudent test with a duplicate student.
     */
    public function testAddDuplicateStudentToCourse() {
        $courseData = CoursesFactory::createCourse(null, null, true, 'optional');
        $student = UserFactory::createUser();
        UserFactory::createPrivacyStatement('course_optional_consent');
        UserFactory::createPrivacyStatement('course_required_consent');

        $adminLogin = OmegaupTestCase::login($courseData['admin']);
        // Student is added to the course
        $response = \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
        ]));

        // User was added to the course, but it is the first access
        $userLogin = OmegaupTestCase::login($student);
        $details = \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
            'auth_token' => $userLogin->auth_token,
            'course_alias' => $courseData['request']['alias']
        ]));
        // Asserting isFirstTimeAccess
        $this->assertEquals(
            'optional',
            $details['requestsUserInformation']
        );
        $this->assertEquals(1, $details['isFirstTimeAccess']);

        $gitObjectId = $details['statements']['privacy']['gitObjectId'];
        $statementType = $details['statements']['privacy']['statementType'];
        // Add the same student. It only updates share_user_information field.
        $response = \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
            'share_user_information' => 1,
            'git_object_id' => $gitObjectId,
            'statement_type' => $statementType,
        ]));

        // Asserting shouldShowResults is on, because admin cannot update share_user_information
        $this->assertEquals(1, $details['isFirstTimeAccess']);

        // User join course for first time.
        \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $userLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
            'share_user_information' => 1,
            'privacy_git_object_id' => $gitObjectId,
            'statement_type' => $statementType,
        ]));

        // User join course twice.
        \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $userLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
            'share_user_information' => 1,
            'privacy_git_object_id' => $gitObjectId,
            'statement_type' => $statementType,
        ]));

        // User agrees sharing his information
        $details = \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
            'auth_token' => $userLogin->auth_token,
            'course_alias' => $courseData['request']['alias']
        ]));
        // Asserting shouldShowResults is off
        $this->assertEquals(0, $details['shouldShowResults']);
    }

    /**
     * Basic apiRemoveStudent test
     */
    public function testRemoveStudentFromCourse() {
        $courseData = CoursesFactory::createCourse();
        $student = UserFactory::createUser();

        $adminLogin = OmegaupTestCase::login($courseData['admin']);
        $response = \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
        ]));
        $this->assertEquals('ok', $response['status']);

        $response = \OmegaUp\Controllers\Course::apiRemoveStudent(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias']
        ]));
        $this->assertEquals('ok', $response['status']);

        // Validate student was removed.
        $course = \OmegaUp\DAO\Courses::getByAlias($courseData['course_alias']);
        $this->assertNotNull($course);

        $studentsInGroup = \OmegaUp\DAO\GroupsIdentities::getByGroupId($course->group_id);

        $this->assertNotNull($studentsInGroup);
        $this->assertEquals(0, count($studentsInGroup));
    }

    /**
     * Students can only be added by course admins
     *
     * @expectedException \OmegaUp\Exceptions\ForbiddenAccessException
     */
    public function testAddStudentNonAdmin() {
        $courseData = CoursesFactory::createCourse();
        $student = UserFactory::createUser();
        $nonAdminUser = UserFactory::createUser();

        $nonAdminLogin = OmegaupTestCase::login($nonAdminUser);
        \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $nonAdminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
            ]));
    }

    /**
     * Can't self-register unless Public
     * @expectedException \OmegaUp\Exceptions\ForbiddenAccessException
     */
    public function testSelfAddStudentNoPublic() {
        $courseData = CoursesFactory::createCourse();
        $student = UserFactory::createUser();

        $login = OmegaupTestCase::login($student);
        \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
            ]));
    }

    /**
     * Can self-register if course is Public
     */
    public function testSelfAddStudentPublic() {
        $courseData = CoursesFactory::createCourse(null, null, true /*public*/);
        $student = UserFactory::createUser();
        $identity = \OmegaUp\DAO\Identities::getByPK($student->main_identity_id);

        $login = OmegaupTestCase::login($student);
        $response = \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
            ]));

        $this->assertEquals('ok', $response['status']);

        // Validate student was added
        $course = \OmegaUp\DAO\Courses::getByAlias($courseData['course_alias']);
        $this->assertNotNull($course);

        $studentsInGroup = \OmegaUp\DAO\GroupsIdentities::getByPK(
            $course->group_id,
            $identity->identity_id
        );

        $this->assertNotNull($studentsInGroup);
    }

    /**
     * Test showIntro with public and private contests
     */
    public function testShouldShowIntro() {
        $courseDataPrivate = CoursesFactory::createCourse();
        $courseDataPublic = CoursesFactory::createCourse(null, null, true);
        $student = UserFactory::createUser();

        // Before or after adding student to private course, intro should not show
        $studentLogin = OmegaupTestCase::login($student);
        try {
            \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
                'auth_token' => $studentLogin->auth_token,
                'course_alias' => $courseDataPrivate['course_alias']
                ]));
        } catch (\OmegaUp\Exceptions\ForbiddenAccessException $e) {
            // OK!
        }

        $adminLogin = OmegaupTestCase::login($courseDataPrivate['admin']);
        $response = \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseDataPrivate['course_alias'],
            ]));

        // Before or after adding student to private course, intro should not show
        $studentLogin = OmegaupTestCase::login($student);
        $details = \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
            'auth_token' => $studentLogin->auth_token,
            'course_alias' => $courseDataPrivate['course_alias']
            ]));
        $this->assertEquals(false, $details['shouldShowResults']);

        // Before adding student to public course, intro should show
        $studentLogin = OmegaupTestCase::login($student);
        $details = \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
            'auth_token' => $studentLogin->auth_token,
            'course_alias' => $courseDataPublic['course_alias']
            ]));
        $this->assertEquals('ok', $details['status']);

        $adminLogin = OmegaupTestCase::login($courseDataPublic['admin']);
        $response = \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseDataPublic['course_alias'],
            ]));
        $details = \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
            'auth_token' => $studentLogin->auth_token,
            'course_alias' => $courseDataPublic['course_alias']
            ]));
        // After adding student to public course, intro should not show
        $this->assertEquals(false, $details['shouldShowResults']);
    }

    /**
     * User accepts teacher
     */
    public function testUserAcceptsTeacher() {
        $courseData = CoursesFactory::createCourse();
        $student = UserFactory::createUser();

        // Admin adds user into the course
        $adminLogin = OmegaupTestCase::login($courseData['admin']);
        \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
        ]));

        // User enters the course and intro details must be shown.
        $studentLogin = OmegaupTestCase::login($student);
        $details = \OmegaUp\Controllers\Course::apiIntroDetails(new \OmegaUp\Request([
            'auth_token' => $studentLogin->auth_token,
            'course_alias' => $courseData['course_alias'],
        ]));
        $this->assertEquals(true, $details['shouldShowAcceptTeacher']);

        $gitObjectId = $details['statements']['acceptTeacher']['gitObjectId'];

        // User joins the course and accepts the organizer as teacher
        \OmegaUp\Controllers\Course::apiAddStudent(new \OmegaUp\Request([
            'auth_token' => $studentLogin->auth_token,
            'usernameOrEmail' => $student->username,
            'course_alias' => $courseData['course_alias'],
            'accept_teacher_git_object_id' => $gitObjectId,
            'accept_teacher' => true,
        ]));
        $details = \OmegaUp\Controllers\Course::getIntroDetails(new \OmegaUp\Request([
            'auth_token' => $studentLogin->auth_token,
            'course_alias' => $courseData['course_alias']
        ]));

        // After adding student to course, intro should not show
        $this->assertArrayNotHasKey('shouldShowAcceptTeacher', $details);
    }
}
