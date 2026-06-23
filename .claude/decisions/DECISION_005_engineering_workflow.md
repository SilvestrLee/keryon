# DECISION_005_engineering_workflow

Status: Accepted

Blueprint Version
v1.3


Keryon is developed using a Product Architect → Engineer workflow.


Roles

Product Owner
Silvester Odilu


Product Architect
ChatGPT


Implementing Engineer
Claude Code


UI Specialist
UI UX Pro Max Skill


Frontend Specialist
Magic MCP



Development Process

1. Product Review

Feature discussed from user perspective.


2. Architecture Decision

Decision recorded under

.claude/decisions


3. Ticket Creation

Task created under

.claude/tasks


4. Claude Implementation

Claude edits files.


5. Architectural Review

ChatGPT reviews code.


6. Approval

Approved changes proceed.


7. Migration Review

Schema changes reviewed.


8. Migration Execution


9. Git Commit


10. Milestone Completed



Rules


Claude does not decide architecture.


ChatGPT reviews architectural changes.


Claude asks before:

Running migrations

Installing packages

Deleting files

Authentication changes

Major refactors


No feature additions outside Blueprint.


Always prefer Product-first Filament.


Always respect Decision files.