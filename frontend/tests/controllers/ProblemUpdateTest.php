<?php

/**
 * Description of UpdateProblem
 *
 * @author joemmanuel
 */

class UpdateProblemTest extends OmegaupTestCase {
    public function testProblemUpdateLanguages() {
        // Get a problem (with 'es' statements)
        $problemData = ProblemsFactory::createProblem(new ProblemParams([
            'zipName' => OMEGAUP_TEST_RESOURCES_ROOT . 'triangulos.zip',
            'title' => 'Problem Language'
        ]));

        // Update statement
        $login = self::login($problemData['author']);

        $problemLanguages = \OmegaUp\DAO\ProblemsLanguages::getByProblemId(
            $problemData['problem']->problem_id
        );
        // This problem only has one language at this point
        $this->assertEquals(1, count($problemLanguages));

        \OmegaUp\Controllers\Problem::apiUpdateStatement(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'message' => 'New statement is now more fun',
            'statement' => 'This is the new statement',
            'lang' => 'en'
        ]));

        // The problem has two languages at this point
        $problemLanguages = \OmegaUp\DAO\ProblemsLanguages::getByProblemId(
            $problemData['problem']->problem_id
        );
        $this->assertEquals(2, count($problemLanguages));
    }

    public function testUpdateProblemTitleAndContents() {
        // Get a problem
        $problemData = ProblemsFactory::createProblem();

        // Get a contest
        $contestData = ContestsFactory::createContest();

        // Add the problem to the contest
        ContestsFactory::addProblemToContest($problemData, $contestData);

        // Create our contestant
        $contestant = UserFactory::createUser();

        // Create a run
        $runData[0] = RunsFactory::createRun($problemData, $contestData, $contestant);
        \OmegaUp\Time::setTimeForTesting(\OmegaUp\Time::get() + 60);
        $runData[1] = RunsFactory::createRunToProblem($problemData, $contestant);

        // Grade the run
        RunsFactory::gradeRun($runData[0]);
        RunsFactory::gradeRun($runData[1]);

        // Get File Uploader Mock and tell Omegaup API to use it
        \OmegaUp\FileHandler::setFileUploaderForTesting($this->createFileUploaderMock());

        // Update Problem calls grader to rejudge, we need to detour grader calls
        // We will submit 2 runs to the problem, a call to grader to rejudge them
        $detourGrader = new ScopedGraderDetour();

        // Set file upload context
        $login = self::login($problemData['author']);
        $_FILES['problem_contents']['tmp_name'] = OMEGAUP_TEST_RESOURCES_ROOT.'triangulos.zip';
        $newTitle = 'new title';
        $response = \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'title' => $newTitle,
            'time_limit' => 12345,
            'problem_alias' => $problemData['request']['problem_alias'],
            'message' => 'Changed some properties',
        ]));

        // Verify data in DB
        $problems = \OmegaUp\DAO\Problems::getByTitle($newTitle);

        // Check that we only retreived 1 element
        $this->assertEquals(1, count($problems));

        // Validate rsponse
        $this->assertEquals('ok', $response['status']);
        $this->assertEquals(true, $response['rejudged']);

        {
            $problemArtifacts = new \OmegaUp\ProblemArtifacts($problemData['request']['problem_alias']);

            // Verify problem contents were copied
            $this->assertTrue($problemArtifacts->exists('cases'));
            $this->assertTrue($problemArtifacts->exists('statements/es.markdown'));
            $this->assertFalse($problemArtifacts->exists('examples/sample.in'));

            // Check update in statements
            $statement = $problemArtifacts->get('statements/es.markdown');
            $this->assertContains('perímetro', $statement);

            $problemDistribSettings = json_decode(
                $problemArtifacts->get('settings.distrib.json'),
                true /* assoc */
            );

            // This example comes from the problem statement.
            $this->assertEquals(
                [
                    'statement_001' => [
                        'in' => "6\n2 3 2 3 2 4",
                        'out' => '10',
                        'weight' => 1,
                    ],
                ],
                $problemDistribSettings['cases']
            );
        }

        // Call API again to add an example, should not trigger rejudge.
        $_FILES['problem_contents']['tmp_name'] = OMEGAUP_TEST_RESOURCES_ROOT.'triangulos-examples.zip';
        $response = \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'message' => 'Add example',
        ]));
        $this->assertEquals('ok', $response['status']);
        $this->assertEquals(false, $response['rejudged']);
        {
            $problemArtifacts = new \OmegaUp\ProblemArtifacts($problemData['request']['problem_alias']);

            // Verify problem contents were copied
            $this->assertTrue($problemArtifacts->exists('cases'));
            $this->assertTrue($problemArtifacts->exists('statements/es.markdown'));
            $this->assertTrue($problemArtifacts->exists('examples/sample.in'));

            // Check update in statements
            $statement = $problemArtifacts->get('statements/es.markdown');
            $this->assertContains('perímetro', $statement);

            $problemDistribSettings = json_decode(
                $problemArtifacts->get('settings.distrib.json'),
                true /* assoc */
            );
            $this->assertEquals(
                [
                    'sample' => [
                        'in' => "6\n2 3 2 3 2 4\n",
                        'out' => "10\n",
                        'weight' => 1
                    ],
                ],
                $problemDistribSettings['cases']
            );
        }

        $this->assertEquals(2, $detourGrader->getGraderCallCount());
    }

    public function testUpdateProblemSettings() {
        // Get a problem with a run.
        $problemData = ProblemsFactory::createProblem();
        $problemAlias = $problemData['request']['problem_alias'];
        $contestant = UserFactory::createUser();
        $runData[0] = RunsFactory::createRunToProblem($problemData, $contestant);
        RunsFactory::gradeRun($runData[0]);

        {
            $problemArtifacts = new \OmegaUp\ProblemArtifacts($problemAlias);
            $this->assertTrue($problemArtifacts->exists('cases'));
            $this->assertTrue($problemArtifacts->exists('statements/es.markdown'));
            $problemSettings = json_decode($problemArtifacts->get('settings.json'));
            $this->assertEquals(
                3,
                count($problemSettings->Cases)
            );
            $this->assertEquals(
                ($problemData['request']['time_limit'] / 1000) . 's',
                $problemSettings->Limits->TimeLimit
            );
        }

        // Update Problem calls grader to rejudge, we need to detour grader calls
        $detourGrader = new ScopedGraderDetour();

        // Call API to update time limit.
        $newTimeLimit = 12345;
        $login = self::login($problemData['author']);
        unset($_FILES['problem_contents']);
        $response = \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'time_limit' => $newTimeLimit,
            'problem_alias' => $problemAlias,
            'message' => 'Increased time limit',
        ]));

        // Validate response
        $this->assertEquals('ok', $response['status']);
        $this->assertTrue(
            $response['rejudged'],
            'Problem should have been rejudged'
        );

        // Verify problem settings were set.
        {
            $problemArtifacts = new \OmegaUp\ProblemArtifacts($problemAlias);
            $this->assertTrue($problemArtifacts->exists('cases'));
            $this->assertTrue($problemArtifacts->exists('statements/es.markdown'));
            $problemSettings = json_decode($problemArtifacts->get('settings.json'));
            $this->assertEquals(
                3,
                count($problemSettings->Cases)
            );
            $this->assertEquals(
                ($newTimeLimit / 1000.0) . 's',
                $problemSettings->Limits->TimeLimit
            );
        }

        $this->assertEquals(1, $detourGrader->getGraderCallCount());
    }

    public function testUpdateProblemWithValidLanguages() {
        // Get a problem
        $problemData = ProblemsFactory::createProblem(new ProblemParams([
            'title' => 'valid-languages'
        ]));

        $languages = 'hs,java,pl';

        // Call API
        $login = self::login($problemData['author']);
        $response = \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'languages' => $languages,
            'problem_alias' => $problemData['request']['problem_alias'],
            'message' => 'Changed alias and languages',
        ]));

        // Verify data in DB
        $problem = \OmegaUp\DAO\Problems::getByAlias($problemData['request']['problem_alias']);

        // Check that we only retrieved 1 element
        $this->assertNotNull($problem);
        $this->assertEqualSets($languages, $problem->languages);

        // Validate response
        $this->assertEquals('ok', $response['status']);
    }

    /**
     * @expectedException \OmegaUp\Exceptions\InvalidParameterException
     */
    public function testUpdateProblemWithInvalidLanguages() {
        // Get a problem
        $problemData = ProblemsFactory::createProblem();

        $login = self::login($problemData['author']);
        $r = new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'languages' => 'cows,hs,java,pl',
            'problem_alias' => $problemData['request']['alias'],
            'message' => 'Changed invalid languages',
        ]);

        //Call API
        $response = \OmegaUp\Controllers\Problem::apiUpdate($r);
    }

    /**
     * Test apiUpdateStatement
     */
    public function testProblemStatementUpdate() {
        // Get a problem (with 'es' statements)
        $problemData = ProblemsFactory::createProblem(new ProblemParams([
            'zipName' => OMEGAUP_TEST_RESOURCES_ROOT . 'triangulos.zip'
        ]));

        // Update statement
        $statement = 'This is the new statement \$x\$';
        $login = self::login($problemData['author']);
        $response = \OmegaUp\Controllers\Problem::apiUpdateStatement(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'message' => 'Statement is now more fun',
            'statement' => $statement
        ]));

        $this->assertEquals($response['status'], 'ok');

        // Check statment contents
        $problemArtifacts = new \OmegaUp\ProblemArtifacts($problemData['request']['problem_alias']);

        $statementMarkdownContents = $problemArtifacts->get('statements/es.markdown');

        $this->assertContains($statement, $statementMarkdownContents);
    }

    /**
     * Test apiUpdateSolution
     */
    public function testProblemSolutionUpdate() {
        $problemData = ProblemsFactory::createProblem();

        // Update solution
        $solution = 'La nueva solución \$x\$';
        $login = self::login($problemData['author']);
        $response = \OmegaUp\Controllers\Problem::apiUpdateSolution(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'message' => 'Solution modified for test.',
            'solution' => $solution
        ]));

        $this->assertEquals($response['status'], 'ok');

        // Check solution contents
        $response = \OmegaUp\Controllers\Problem::apiSolution(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
        ]));

        $this->assertContains($solution, $response['solution']['markdown']);
    }

    /**
     * Test apiUpdateStatement with embedded imgs via data URI
     */
    public function testProblemStatementUpdateWithImagesAsDataURI() {
        // Get a problem (with 'es' statements)
        $problemData = ProblemsFactory::createProblem(new ProblemParams([
            'zipName' => OMEGAUP_TEST_RESOURCES_ROOT . 'triangulos.zip'
        ]));

        // Update statement
        $imgUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==';
        $imgFilename = 'af6a4603e039cca2f6823d287f6c87e561aa6e68.png';

        $statement = "This is the new statement with an image omg ![Alt text]($imgUri \"Optional title\")\n";
        $login = self::login($problemData['author']);
        $response = \OmegaUp\Controllers\Problem::apiUpdateStatement(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'message' => 'Statement now contains images',
            'statement' => $statement
        ]));

        $this->assertEquals($response['status'], 'ok');

        // Check statment contents
        $problemArtifacts = new \OmegaUp\ProblemArtifacts($problemData['request']['problem_alias']);
        $statementMarkdownContents = $problemArtifacts->get('statements/es.markdown');

        $this->assertEquals($statement, $statementMarkdownContents);
    }

    /**
     * Tests update problem: on error, original contents should persist
     */
    public function testUpdateProblemFailed() {
        // Get a problem
        $problemData = ProblemsFactory::createProblem();

        // Get File Uploader Mock and tell Omegaup API to use it
        \OmegaUp\FileHandler::setFileUploaderForTesting($this->createFileUploaderMock());

        // Update Problem calls grader to rejudge, we need to detour grader calls
        $detourGrader = new ScopedGraderDetour();

        // Set file upload context. This problem should fail
        $_FILES['problem_contents']['tmp_name'] = OMEGAUP_TEST_RESOURCES_ROOT.'nostmt.zip';

        // Call API. Should fail
        try {
            $login = self::login($problemData['author']);
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'title' => 'new title',
                'time_limit' => 12345,
                'problem_alias' => $problemData['request']['problem_alias'],
                'message' => 'This should fail',
            ]));
            $this->fail('Expected update to fail');
        } catch (\OmegaUp\Exceptions\ProblemDeploymentFailedException $e) {
            $this->assertEquals('problemDeployerNoStatements', $e->getMessage());
        }

        // Verify contents were not erased
        $problemArtifacts = new \OmegaUp\ProblemArtifacts($problemData['request']['problem_alias']);

        $this->assertTrue($problemArtifacts->exists('cases'));
        $this->assertTrue($problemArtifacts->exists('statements/es.markdown'));

        // Check statements still is the original one
        $statement = $problemArtifacts->get('statements/es.markdown');
        $this->assertContains('# Entrada', $statement);

        $this->assertEquals(0, $detourGrader->getGraderCallCount());
    }

    /**
     * Tests problem admin can edit a problem
     */
    public function testUpdateProblemWithProblemAdmin() {
        // Get a problem
        $problemData = ProblemsFactory::createProblem();

        // Create our new admin
        $problemAdmin = UserFactory::createUser();

        // Add admin to the problem
        $adminLogin = self::login($problemData['author']);
        $response = \OmegaUp\Controllers\Problem::apiAddAdmin(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $problemAdmin->username,
            'problem_alias' => $problemData['request']['problem_alias'],
        ]));

        $this->assertEquals('ok', $response['status']);

        //Call API
        $newTitle = 'new title coadmin';
        $login = self::login($problemAdmin);
        $response = \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'title' => $newTitle,
            'message' => 'Admin powers',
        ]));

        // Verify data in DB
        $problems = \OmegaUp\DAO\Problems::getByTitle($newTitle);

        $this->assertTrue(!is_null($problems));
    }

    /**
     * Tests removed problem admin can't edit a problem anymore
     *
     * @expectedException \OmegaUp\Exceptions\ForbiddenAccessException
     */
    public function testUpdateProblemWithRemovedProblemAdmin() {
        // Get a problem
        $problemData = ProblemsFactory::createProblem();

        // Create our new admin
        $problemAdmin = UserFactory::createUser();

        // Add admin to the problem
        $adminLogin = self::login($problemData['author']);
        $response = \OmegaUp\Controllers\Problem::apiAddAdmin(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $problemAdmin->username,
            'problem_alias' => $problemData['request']['problem_alias'],
        ]));

        $this->assertEquals('ok', $response['status']);

        // Then remove the user
        $response = \OmegaUp\Controllers\Problem::apiRemoveAdmin(new \OmegaUp\Request([
            'auth_token' => $adminLogin->auth_token,
            'usernameOrEmail' => $problemAdmin->username,
            'problem_alias' => $problemData['request']['problem_alias'],
        ]));
        $this->assertEquals('ok', $response['status']);

        //Call API
        $newTitle = 'new title coadmin';
        $login = self::login($problemAdmin);
        $response = \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'title' => $newTitle,
            'message' => 'Non-admin powers',
        ]));

        // Verify data in DB
        $problems = \OmegaUp\DAO\Problems::getByTitle($newTitle);
    }

    /**
     * Tests problem admins list API
     */
    public function testProblemAdmins() {
        // Get a problem
        $problemData = ProblemsFactory::createProblem();

        // Create our new admin
        $problemAdmin = UserFactory::createUser();

        // Add admin to the problem
        $login = self::login($problemData['author']);
        $response = \OmegaUp\Controllers\Problem::apiAddAdmin(new \OmegaUp\Request([
            'usernameOrEmail' => $problemAdmin->username,
            'problem_alias' => $problemData['request']['problem_alias'],
            'auth_token' => $login->auth_token,
        ]));

        $this->assertEquals('ok', $response['status']);

        // Get the list of admins
        $response = \OmegaUp\Controllers\Problem::apiAdmins(new \OmegaUp\Request([
            'problem_alias' => $problemData['request']['problem_alias'],
            'auth_token' => $login->auth_token,
        ]));

        $adminFound = false;
        $ownerFound = false;
        foreach ($response['admins'] as $adminEntry) {
            if ($adminEntry['username'] == $problemAdmin->username) {
                $adminFound = true;
            }

            if ($adminEntry['username'] == $problemData['author']->username) {
                $ownerFound = true;
            }
        }
        $this->assertTrue($adminFound);
        $this->assertTrue($ownerFound);
    }

    /**
     * Test reviewers can do some problem-related tasks.
     */
    public function testProblemUpdateByReviewer() {
        // Create a private problem.
        $problemData = ProblemsFactory::createProblem(new ProblemParams([
            'zipName' => OMEGAUP_TEST_RESOURCES_ROOT . 'triangulos.zip',
            'visibility' => 0
        ]));

        // Normal user shouldn't even be able to see the problem.
        $reviewer = UserFactory::createUser();
        $login = self::login($reviewer);
        try {
            \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problemData['request']['problem_alias'],
            ]));
            $this->fail('Should not have been able to see the problem');
        } catch (\OmegaUp\Exceptions\ForbiddenAccessException $e) {
            $this->assertEquals($e->getMessage(), 'problemIsPrivate');
        }

        // Promote to reviewer, can see the problem now.
        UserFactory::addSystemRole($reviewer, \OmegaUp\Authorization::REVIEWER_ROLE);
        $response = \OmegaUp\Controllers\Problem::apiList(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
        ]));
        $this->assertEquals($response['status'], 'ok');
        $this->assertArrayContainsWithPredicate($response['results'], function ($problem) use (&$problemData) {
            return $problem['alias'] == $problemData['request']['problem_alias'];
        });
        $response = \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
        ]));
        $this->assertEquals($response['status'], 'ok');

        // Update statement
        $statement = 'This is the new statement \$x\$';
        $response = \OmegaUp\Controllers\Problem::apiUpdateStatement(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'message' => 'Statement is now more fun',
            'statement' => $statement
        ]));
        $this->assertEquals($response['status'], 'ok');

        // Add a tag
        $response = \OmegaUp\Controllers\Problem::apiAddTag(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['request']['problem_alias'],
            'name' => 'test',
        ]));
        $this->assertEquals($response['status'], 'ok');
    }

    /**
     * Tests that problems cannot change their visibility under some scenarios.
     */
    public function testProblemUpdateVisibility() {
        // Create a public problem
        $problemData = ProblemsFactory::createProblem();
        $login = self::login($problemData['author']);
        $problem = $problemData['problem'];

        // Make it private.
        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PRIVATE,
            'message' => 'public -> private',
        ]));

        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PRIVATE,
            'message' => 'no-op',
        ]));
        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'message' => 'no-op',
        ]));

        // Make it public
        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC,
            'message' => 'private -> public',
        ]));

        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC,
            'message' => 'no-op',
        ]));
        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'message' => 'no-op',
        ]));

        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC_BANNED,
            'message' => 'public -> banned',
        ]));

        try {
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
                'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PROMOTED,
                'message' => 'public -> promoted',
            ]));
            $this->fail('Cannot ban problem from API');
        } catch (\OmegaUp\Exceptions\InvalidParameterException $e) {
        }

        // Ban the problem.
        $problem->visibility = \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC_BANNED;
        \OmegaUp\DAO\Problems::update($problem);

        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC_BANNED,
            'message' => 'no-op',
        ]));
        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'message' => 'no-op',
        ]));

        try {
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
                'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PRIVATE,
                'message' => 'banned -> private',
            ]));
            $this->fail('Cannot un-ban problem from API');
        } catch (\OmegaUp\Exceptions\InvalidParameterException $e) {
            $this->assertEquals($e->getMessage(), 'qualityNominationProblemHasBeenBanned');
        }

        try {
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
                'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC,
                'message' => 'banned -> public',
            ]));
            $this->fail('Cannot un-ban problem from API');
        } catch (\OmegaUp\Exceptions\InvalidParameterException $e) {
            $this->assertEquals($e->getMessage(), 'qualityNominationProblemHasBeenBanned');
        }

        // Promote the problem.
        $problem->visibility = \OmegaUp\Controllers\Problem::VISIBILITY_PROMOTED;
        \OmegaUp\DAO\Problems::update($problem);

        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PROMOTED,
            'message' => 'no-op',
        ]));
        \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'message' => 'no-op',
        ]));

        try {
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
                'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PRIVATE,
                'message' => 'promoted -> private',
            ]));
            $this->fail('Cannot un-promote problem from API');
        } catch (\OmegaUp\Exceptions\InvalidParameterException $e) {
            $this->assertEquals($e->getMessage(), 'qualityNominationProblemHasBeenPromoted');
        }

        try {
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
                'visibility' => \OmegaUp\Controllers\Problem::VISIBILITY_PUBLIC,
                'message' => 'promoted -> public',
            ]));
            $this->fail('Cannot un-promote problem from API');
        } catch (\OmegaUp\Exceptions\InvalidParameterException $e) {
            $this->assertEquals($e->getMessage(), 'qualityNominationProblemHasBeenPromoted');
        }
    }

    /**
     * Tests tag operations.
     */
    public function testTags() {
        $problemData = ProblemsFactory::createProblem();
        $login = self::login($problemData['author']);
        $this->assertEquals(
            [
                [
                    'name' => 'lenguaje',
                    'public' => '1',
                ],
            ],
            \OmegaUp\Controllers\Problem::apiTags(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problemData['problem']->alias,
            ]))['tags']
        );

        \OmegaUp\Controllers\Problem::apiAddTag(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['problem']->alias,
            'name' => 'foo',
            'public' => 'true',
        ]));
        $this->assertEquals(
            [
                [
                    'name' => 'lenguaje',
                    'public' => '1',
                ],
                [
                    'name' => 'foo',
                    'public' => '1',
                ],
            ],
            \OmegaUp\Controllers\Problem::apiTags(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problemData['problem']->alias,
            ]))['tags']
        );

        \OmegaUp\Controllers\Problem::apiRemoveTag(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problemData['problem']->alias,
            'name' => 'foo',
            'public' => 'true',
        ]));
        $this->assertEquals(
            [
                [
                    'name' => 'lenguaje',
                    'public' => '1',
                ],
            ],
            \OmegaUp\Controllers\Problem::apiTags(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problemData['problem']->alias,
            ]))['tags']
        );

        try {
            \OmegaUp\Controllers\Problem::apiRemoveTag(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problemData['problem']->alias,
                'name' => 'lenguaje',
                'public' => 'true',
            ]));
            $this->fail('Should not have been able to remove restricted tag');
        } catch (\OmegaUp\Exceptions\InvalidParameterException $e) {
            $this->assertEquals('tagRestricted', $e->getMessage());
        }
    }

    /**
     * Tests problem version update.
     */
    public function testProblemVersionUpdate() {
        $problemData = ProblemsFactory::createProblem();
        $problem = $problemData['problem'];
        $contestant = UserFactory::createUser();
        $runData = RunsFactory::createRunToProblem($problemData, $contestant);
        RunsFactory::gradeRun($runData, 1.0, 'AC');

        $login = self::login($problemData['author']);
        $originalVersionData = \OmegaUp\Controllers\Problem::apiVersions(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
        ]));
        $originalDetails = \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
        ]));

        // Change the problem to something completely different.
        {
            $_FILES['problem_contents']['tmp_name'] = OMEGAUP_TEST_RESOURCES_ROOT.'mrkareltastic.zip';
            $detourGrader = new ScopedGraderDetour();
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
                'message' => 'Changed to mrkareltastic',
                'validator' => 'token',
                'time_limit' => 1000,
                'overall_wall_time_limit' => 30000,
                'validator_time_limit' => 0,
                'extra_wall_time' => 1000,
                'memory_limit' => 64000,
                'output_limit' => 20480,
            ]));
            $this->assertEquals(1, $detourGrader->getGraderCallCount());
        foreach ($detourGrader->getRuns() as $run) {
            RunsFactory::gradeRun(null, 0, 'WA', null, null, $run->run_id);
        }
        }

        $modifiedVersionData = \OmegaUp\Controllers\Problem::apiVersions(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
        ]));
        $this->assertNotEquals(
            $originalVersionData['published'],
            $modifiedVersionData['published']
        );
        $modifiedDetails = \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
        ]));
        $this->assertNotEquals(
            $originalDetails['statement'],
            $modifiedDetails['statement']
        );

        // Change it back to the original problem. Should not cause any new
        // rejudges, but current_version should go back to the original.
        {
            $_FILES['problem_contents']['tmp_name'] = OMEGAUP_TEST_RESOURCES_ROOT.'testproblem.zip';
            $detourGrader = new ScopedGraderDetour();
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
                'message' => 'Changed back',
                'validator' => 'token',
                'time_limit' => 5000,
                'overall_wall_time_limit' => 60000,
                'validator_time_limit' => 30000,
                'extra_wall_time' => 0,
                'memory_limit' => 32000,
                'output_limit' => 10240,
            ]));
            $this->assertEquals(0, $detourGrader->getGraderCallCount());
        }

        $restoredVersionData = \OmegaUp\Controllers\Problem::apiVersions(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
        ]));
        $this->assertNotEquals(
            $originalVersionData['published'],
            $restoredVersionData['published']
        );
        $restoredDetails = \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
        ]));
        $this->assertEquals(
            $originalDetails['statement'],
            $restoredDetails['statement']
        );

        // Now that the problem is set up, we'll attempt to change the version.
        \OmegaUp\Controllers\Problem::apiSelectVersion(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'commit' => $originalVersionData['published'],
        ]));
        $this->assertEquals(
            $originalDetails,
            \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
            ]))
        );
        $this->assertEquals(
            $originalVersionData['published'],
            \OmegaUp\Controllers\Problem::apiVersions(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
            ]))['published']
        );
        $this->assertEquals(
            1.0,
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($runData['response']['guid'])->current_run_id
            )->score
        );

        // Change it to the second version.
        \OmegaUp\Controllers\Problem::apiSelectVersion(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'commit' => $modifiedVersionData['published'],
        ]));
        $this->assertEquals(
            $modifiedDetails,
            \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
            ]))
        );
        $this->assertEquals(
            $modifiedVersionData['published'],
            \OmegaUp\Controllers\Problem::apiVersions(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
            ]))['published']
        );
        $this->assertEquals(
            0.0,
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($runData['response']['guid'])->current_run_id
            )->score
        );

        // Change it back to the restored version.
        \OmegaUp\Controllers\Problem::apiSelectVersion(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $problem->alias,
            'commit' => $restoredVersionData['published'],
        ]));
        $this->assertEquals(
            $restoredDetails,
            \OmegaUp\Controllers\Problem::apiDetails(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
            ]))
        );
        $this->assertEquals(
            $restoredVersionData['published'],
            \OmegaUp\Controllers\Problem::apiVersions(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
            ]))['published']
        );
        $this->assertEquals(
            1.0,
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($runData['response']['guid'])->current_run_id
            )->score
        );
    }

    private function updateProblemsetProblemWithRuns(
        string $updatePublished,
        ?\OmegaUp\DAO\VO\Users $problemAuthor = null,
        ?\OmegaUp\DAO\VO\Users $contestDirector = null,
        ?\OmegaUp\DAO\VO\Users $contestAdmin = null
    ) {
        $originalTime = \OmegaUp\Time::get();
        try {
            $problemData = ProblemsFactory::createProblem(new ProblemParams([
                'author' => $problemAuthor,
            ]));
            $problem = $problemData['problem'];
            $contestant = UserFactory::createUser();

            \OmegaUp\Time::setTimeForTesting($originalTime - 30 * 60);

            // Create a standalone run.
            $pastStandaloneRunData = RunsFactory::createRunToProblem($problemData, $contestant);
            RunsFactory::gradeRun($pastStandaloneRunData);

            // Create a contest in the past with one run.
            $pastContestData = ContestsFactory::createContest(new ContestParams([
                'start_time' => $originalTime - 60 * 60,
                'finish_time' => $originalTime - 5 * 60,
                'contestDirector' => $contestDirector,
            ]));
            ContestsFactory::addProblemToContest($problemData, $pastContestData);
            ContestsFactory::addUser($pastContestData, $contestant);
            if (!is_null($contestAdmin)) {
                ContestsFactory::addAdminUser($pastContestData, $contestAdmin);
            }
            $pastRunData = RunsFactory::createRun($problemData, $pastContestData, $contestant);
            RunsFactory::gradeRun($pastRunData);

            // Now create one in the present with one more run.
            $presentContestData = ContestsFactory::createContest(new ContestParams([
                'start_time' => $originalTime - 60 * 60,
                'finish_time' => $originalTime + 60 * 60,
                'contestDirector' => $contestDirector,
            ]));
            ContestsFactory::addProblemToContest($problemData, $presentContestData);
            ContestsFactory::addUser($presentContestData, $contestant);
            if (!is_null($contestAdmin)) {
                ContestsFactory::addAdminUser($presentContestData, $contestAdmin);
            }
            $presentRunData = RunsFactory::createRun($problemData, $presentContestData, $contestant);
            RunsFactory::gradeRun($presentRunData);

            \OmegaUp\Time::setTimeForTesting($originalTime + 5 * 60);

            $login = self::login($problemData['author']);
            // Change the problem to something completely different.
            $_FILES['problem_contents']['tmp_name'] = OMEGAUP_TEST_RESOURCES_ROOT.'mrkareltastic.zip';
            $detourGrader = new ScopedGraderDetour();
            \OmegaUp\Controllers\Problem::apiUpdate(new \OmegaUp\Request([
                'auth_token' => $login->auth_token,
                'problem_alias' => $problem->alias,
                'message' => 'Changed to mrkareltastic',
                'validator' => 'token',
                'time_limit' => 1000,
                'overall_wall_time_limit' => 30000,
                'validator_time_limit' => 0,
                'extra_wall_time' => 1000,
                'memory_limit' => 64000,
                'output_limit' => 20480,
                'update_published' => $updatePublished,
            ]));
            // Runs are only added when the publishing mode is not none.
            $this->assertEquals(
                $updatePublished == \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_NONE ? 0 : 3,
                $detourGrader->getGraderCallCount()
            );
            foreach ($detourGrader->getRuns() as $run) {
                RunsFactory::gradeRun(null, 0, 'WA', null, null, $run->run_id);
            }

            return [
                'pastRunData' => $pastRunData,
                'presentRunData' => $presentRunData,
                'pastStandaloneRunData' => $pastStandaloneRunData,
                'pastProblemData' => $problemData,
                'pastContestData' => $pastContestData,
                'presentContestData' => $presentContestData,
            ];
        } finally {
            \OmegaUp\Time::setTimeForTesting($originalTime);
        }
    }

    /**
     * Tests problem version update in a problemset context with no updates to
     * the published branch. All runs are kept as-is.
     */
    public function testProblemInProblemsetVersionUpdateNone() {
        $result = $this->updateProblemsetProblemWithRuns(\OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_NONE);
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );

        $owner = UserFactory::createUser();
        $result = $this->updateProblemsetProblemWithRuns(
            \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_NONE,
            $owner,
            $owner
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );
    }

    /**
     * Tests problem version update in a problemset context with non-problemset
     * as the update option. Runs are also kept as-is.
     */
    public function testProblemInProblemsetVersionUpdateNonProblemset() {
        $result = $this->updateProblemsetProblemWithRuns(
            \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_NON_PROBLEMSET
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );

        $owner = UserFactory::createUser();
        $result = $this->updateProblemsetProblemWithRuns(
            \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_NON_PROBLEMSET,
            $owner,
            $owner
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );

        // Ensure that rolling back the version change would make a difference.
        $login = self::login($result['pastProblemData']['author']);
        $diffResult = \OmegaUp\Controllers\Problem::apiRunsDiff(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $result['pastProblemData']['problem']->alias,
            'version' => $result['pastProblemData']['problem']->current_version,
        ]));
        $this->assertEquals(1, count($diffResult['diff']));
        $this->assertEquals(
            $result['pastStandaloneRunData']['response']['guid'],
            $diffResult['diff'][0]['guid']
        );
        $this->assertEquals('WA', $diffResult['diff'][0]['old_verdict']);
        $this->assertEquals('AC', $diffResult['diff'][0]['new_verdict']);
    }

    /**
     * Tests problem version update in a problemset context with owned problemset
     * as the update option. Only the runs from problemsets that the problem
     * owner also has edit access will be updated.
     */
    public function testProblemInProblemsetVersionUpdateOwnedProblemsets() {
        $result = $this->updateProblemsetProblemWithRuns(
            \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_OWNED_PROBLEMSETS
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );

        $owner = UserFactory::createUser();
        $result = $this->updateProblemsetProblemWithRuns(
            \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_OWNED_PROBLEMSETS,
            $owner,
            $owner
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'WA',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );

        // Ensure that the version change would make a difference.
        $login = self::login($result['pastProblemData']['author']);
        $presentProblem = \OmegaUp\DAO\Problems::getByAlias($result['pastProblemData']['problem']->alias);
        $diffResult = \OmegaUp\Controllers\Contest::apiRunsDiff(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $result['pastProblemData']['problem']->alias,
            'contest_alias' => $result['pastContestData']['contest']->alias,
            'version' => $presentProblem->current_version,
        ]));
        $this->assertEquals(1, count($diffResult['diff']));
        $this->assertEquals(
            $result['pastRunData']['response']['guid'],
            $diffResult['diff'][0]['guid']
        );
        $this->assertEquals('AC', $diffResult['diff'][0]['old_verdict']);
        $this->assertEquals('WA', $diffResult['diff'][0]['new_verdict']);

        // Changing the version of the problemset to whatever it is currently
        // should not have any visible effect.
        \OmegaUp\Controllers\Contest::apiAddProblem(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $result['pastProblemData']['problem']->alias,
            'contest_alias' => $result['pastContestData']['contest']->alias,
            'commit' => $result['pastProblemData']['problem']->commit,
            'points' => 100,
            'order_in_contest' => 1,
        ]));
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );

        // Now explicitly change the version of the problemset.
        \OmegaUp\Controllers\Contest::apiAddProblem(new \OmegaUp\Request([
            'auth_token' => $login->auth_token,
            'problem_alias' => $result['pastProblemData']['problem']->alias,
            'contest_alias' => $result['pastContestData']['contest']->alias,
            'commit' => $presentProblem->commit,
            'points' => 100,
            'order_in_contest' => 1,
        ]));
        $this->assertEquals(
            'WA',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
    }

    /**
     * Tests problem version update in a problemset context with owned problemset
     * as the update option. Only the runs from problemsets that the problem
     * owner also has edit access will be updated.
     */
    public function testProblemInProblemsetVersionUpdateEditableProblemsets() {
        $result = $this->updateProblemsetProblemWithRuns(
            \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_EDITABLE_PROBLEMSETS
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );

        $owner = UserFactory::createUser();
        $result = $this->updateProblemsetProblemWithRuns(
            \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_EDITABLE_PROBLEMSETS,
            $owner,
            null,
            $owner
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'WA',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );

        $owner = UserFactory::createUser();
        $result = $this->updateProblemsetProblemWithRuns(
            \OmegaUp\Controllers\Problem::UPDATE_PUBLISHED_EDITABLE_PROBLEMSETS,
            $owner,
            $owner
        );
        $this->assertEquals(
            'AC',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['pastRunData']['response']['guid'])->current_run_id
            )->verdict
        );
        $this->assertEquals(
            'WA',
            \OmegaUp\DAO\Runs::getByPK(
                \OmegaUp\DAO\Submissions::getByGuid($result['presentRunData']['response']['guid'])->current_run_id
            )->verdict
        );
    }
}
