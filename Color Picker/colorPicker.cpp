#include <C:\Users\niko9\Documents\Visual Studio Code\Color Picker\glut.h>
#include <iostream>

const int pickerWidth = 256;
const int pickerHeight = 256;

GLubyte selectedColor[3] = { 255, 0, 0 }; // Initial color (red)

void display() {
    // Clear the screen
    glClear(GL_COLOR_BUFFER_BIT);

    // Draw the color picker rectangle
    glBegin(GL_QUADS);
        glColor3ub(0, 0, 0); // Black background
        glVertex2f(0, 0);
        glVertex2f(pickerWidth, 0);
        glVertex2f(pickerWidth, pickerHeight);
        glVertex2f(0, pickerHeight);
    glEnd();

    // Draw the selected color rectangle
    glBegin(GL_QUADS);
        glColor3ubv(selectedColor);
        glVertex2f(0, 0);
        glVertex2f(20, 0);
        glVertex2f(20, 20);
        glVertex2f(0, 20);
    glEnd();

    glutSwapBuffers();
}

void mouse(int button, int state, int x, int y) {
    if (button == GLUT_LEFT_BUTTON && state == GLUT_DOWN) {
        // Check if the click is within the color picker rectangle
        if (x >= 0 && x < pickerWidth && y >= 0 && y < pickerHeight) {
            selectedColor[0] = x;
            selectedColor[1] = pickerHeight - y; // Flip Y-axis
            selectedColor[2] = 255;
            glutPostRedisplay();
        }
    }
}

int main(int argc, char** argv) {
    glutInit(&argc, argv);
    glutInitDisplayMode(GLUT_DOUBLE | GLUT_RGB);
    glutInitWindowSize(pickerWidth, pickerHeight + 20); // Adjust for selected color rectangle
    glutCreateWindow("Color Picker");
    glutDisplayFunc(display);
    glutMouseFunc(mouse);
    glutMainLoop();
    return 0;
}