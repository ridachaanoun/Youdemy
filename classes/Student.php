<?php
require_once "User.php";
require_once "Course.php"; 
require_once "Category.php"; 

class Student extends User {
    private array $courses = [];

    public function __construct(PDO $dbConnection, int $id) {
        $query = "SELECT * FROM Users WHERE id = :id AND role = 'Student'";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Student with ID $id not found.");
        }

        parent::__construct(
            $user['id'],
            $user['name'],
            $user['email'],
            $user['password'],
            $user['role'],
            $user['status'],
            (bool) $user['is_validated']
        );
    }

    public function enrollInCourse(PDO $dbConnection, int $courseId): bool {
        try {
            $query = "INSERT INTO Students_Courses (student_id, course_id) VALUES (:studentId, :courseId)";
            $stmt = $dbConnection->prepare($query);
            return $stmt->execute([
                ':studentId' => $this->id,
                ':courseId' => $courseId
            ]);
        } catch (PDOException $e) {
            if($e->getCode()=="23000"){
                throw new Exception("you are alrad enrolled this course.", 409);
            }
            throw new Exception("Failed to enroll in the course: " . $e->getMessage());
        }
    }

    public function getEnrolledCourses(PDO $dbConnection): array {
        foreach ($this->courses as $course){
            $course->getEnrolledStudents($dbConnection);
        }
        return $this->courses;
    }

    public function loadCourses(PDO $dbConnection): void {
        $query = "SELECT c.* FROM Courses c 
                  INNER JOIN Students_Courses s ON c.id = s.course_id 
                  WHERE s.student_id = :userId";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute([':userId' => $this->id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $this->courses = [];
        foreach ($result as $courseData) {
            // Fetch category for the course
            $category = null;
            if ($courseData['category_id']) {
                $categoryQuery = "SELECT * FROM Categories WHERE id = :categoryId";
                $categoryStmt = $dbConnection->prepare($categoryQuery);
                $categoryStmt->execute([':categoryId' => $courseData['category_id']]);
                $categoryData = $categoryStmt->fetch(PDO::FETCH_ASSOC);
    
                if ($categoryData) {
                    $category = new Category($categoryData['id'], $categoryData['name']);
                }
            }
    
            // Fetch tags for the course
            $tagsQuery = "SELECT t.id, t.name FROM Tags t
                          INNER JOIN Courses_Tags ct ON t.id = ct.tag_id
                          WHERE ct.course_id = :courseId";
            $tagsStmt = $dbConnection->prepare($tagsQuery);
            $tagsStmt->execute([':courseId' => $courseData['id']]);
            $tagsData = $tagsStmt->fetchAll(PDO::FETCH_ASSOC);
    
            $tags = array_map(fn($tag) => new Tag($tag['id'], $tag['name']), $tagsData);
    
            // Fetch the number of enrolled students for the course
            $studentsQuery = "SELECT u.id, u.name, u.email FROM Users u 
                  INNER JOIN Students_Courses sc ON u.id = sc.student_id
                  WHERE sc.course_id = :courseId";
            $studentsStmt = $dbConnection->prepare($studentsQuery);
            $studentsStmt->execute([':courseId' => $courseData['id']]);
            $student = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Create Course object and fully initialize
            $this->courses[] = new Course(
                $courseData['id'],
                $courseData['title'],
                $courseData['description'],
                $courseData['content'],
                $tags,  // Fully loaded tags
                $category,  // Fully loaded category
                $courseData['teacher_id'],
                $student
            );
        }
    }
    
    
public function deleteStudents_Course(PDO $dbConnection, int $courseId): bool {
    try {
        $stmt = $dbConnection->prepare("DELETE FROM Students_Courses WHERE student_id = :id AND course_id = :idC");
        $stmt->execute([":id" => $this->id, ":idC" => $courseId]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false; 
    }
}

public function delete_Students_Courses(PDO $dbConnection): bool {
    try {
        $stmt = $dbConnection->prepare("DELETE FROM Students_Courses WHERE student_id = :id");
        $stmt->execute([":id" => $this->id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false; 
    }
}

public function deleteStudent(PDO $dbConnection): bool {
    try {
        // delete all enrollments
        $coursesDeleted = $this->delete_Students_Courses($dbConnection);
        
        if (!$coursesDeleted) {
            return false; 
        }
        
        // delete the user 
        $stmt = $dbConnection->prepare("DELETE FROM Users WHERE id = :id");
        $stmt->execute([":id" => $this->id]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false; 
    }
}


}
