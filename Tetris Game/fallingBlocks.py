import pygame

class fallingBlocks:
    def __init__(self, grid, collision_detector, fall_speed=0.27):
        self.grid = grid
        self.collision_detector = collision_detector
        self.fall_speed = fall_speed
        self.fall_time = 0
        self.current_time = 0

    def update(self, tetromino, delta_time):
        """Update the falling mechanics based on the delta time since last frame."""
        self.fall_time += delta_time
        if self.fall_time > self.fall_speed:
            self.fall_time = 0
            return self.move_down(tetromino)

    def move_down(self, tetromino):
        """Move the tetromino down unless there is a collision."""
        if not self.collision_detector.check_collision(tetromino, dx=0, dy=1):
            tetromino.move(0, 1)
            return True
        return False
