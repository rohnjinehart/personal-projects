class collisionDetector:
    def __init__(self, grid):
        self.grid = grid

    def check_collision(self, tetromino, dx=0, dy=0, rotation=None):
        """Check for collision when moving or rotating the Tetromino."""
        x, y = tetromino.position
        shape = tetromino.shape[rotation if rotation is not None else tetromino.rotation]

        for i, row in enumerate(shape):
            for j, cell in enumerate(row):
                if cell:
                    new_x = x + j + dx
                    new_y = y + i + dy

                    # Check if new position is out of bounds
                    if new_x < 0 or new_x >= len(self.grid[0]) or new_y >= len(self.grid):
                        return True

                    # Check if new position collides with existing block in grid
                    if new_y >= 0 and self.grid[new_y][new_x]:
                        return True

        return False