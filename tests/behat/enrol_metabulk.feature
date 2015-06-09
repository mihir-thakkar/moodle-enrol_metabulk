@enrol @enrol_metabulk
Feature: Enrolments are synchronised with meta courses
  In order to simplify enrolments in parent courses
  As a teacher
  I need to be able to set up meta enrolments

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@asd.com |
      | student2 | Student | 2 | student2@asd.com |
      | student3 | Student | 3 | student3@asd.com |
      | student4 | Student | 4 | student4@asd.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1 |
      | Course 2 | C2 |
      | Course 3 | C3 |
    And the following "groups" exist:
      | name | course | idnumber |
      | Groupcourse 1 | C3 | G1 |
      | Groupcourse 2 | C3 | G2 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |
      | student4 | C1 | student |
      | student1 | C2 | student |
      | student2 | C2 | student |
    And I log in as "admin"
    And I navigate to "Manage enrol plugins" node in "Site administration > Plugins > Enrolments"
    And I click on "Enable" "link" in the "Bulk meta course link" "table_row"
    And I am on homepage
    And I follow "Courses"
  @runonlythis
  Scenario: Add meta enrolment instance without groups
    When I follow "Course 3"
    And I add "Bulk meta course link" enrolment method with:
      | Custom instance name  | testname1 |
    And I navigate to "Enrolment methods" node in "Course administration > Users"
    Then I should see "testname1" in the "table.generaltable" "css_element"